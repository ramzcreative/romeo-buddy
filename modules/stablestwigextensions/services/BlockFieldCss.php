<?php

namespace modules\stablestwigextensions\services;

use Craft;
use craft\models\EntryType;
use modules\themepicker\services\BlockRules;
use modules\themepicker\services\ThemeConfig;
use modules\themepicker\services\ThemeRegistry;

/**
 * Generates the CP stylesheet that hides the fields a block can't use: one of its own fields, or a field on a type
 * nested in it (the shared Item, Block Heading, ...), always or only on some of its layouts. The rules themselves come
 * from craft-modules' BlockRules, which reads config/stables/blockfields.php plus the active theme's
 * config/blockfields.json; this class only turns them into CSS. See docs/theme-designer-blocks-spec.md §4.
 *
 * Craft evaluates field conditions against the element being edited and ships no owner-aware rule, so a nested item
 * cannot know which block contains it. CSS can, but where depends on the Matrix field's view mode:
 *
 *   blocks view    the block renders inline as its own .matrixblock, holding its fields and its nested blocks.
 *
 *   cards view     the block opens in a slideout and its own .matrixblock is NOT in that DOM; the form's layout tab,
 *                  a direct child of .so-content, is what identifies the type (:has() reaches back up to it).
 *
 * Every rule targets exactly its own level. A block's own field excludes anything inside a nested .matrixblock, and a
 * nested type's field excludes anything nested deeper, so a same-handle field one level down (Block Heading's
 * `preheading` beside the Item's) is never caught. Tab uids and type handles are per database, so this is generated.
 */
class BlockFieldCss
{
    private const CACHE_TTL = 86400;

    /**
     * Everything a CP request needs, cached: this runs on every CP request, autosaves included. The key covers every
     * input, so nothing needs clearing by hand: the theme, project config's dateModified (any field or entry type
     * change), and the modification time of the site and theme rules, both `_blocks` template folders (a template
     * added or removed changes which blocks are offered) and the code that builds this.
     *
     * @return array{css: string, switchGroups: array<string, string[]>, menuLabels: array<string, string[]>, unoffered: string[], builderFields: string[], nested: array{rules: array<string, string[]>, slideoutTabs: array<string, string>}}
     */
    public function cpPayload(): array
    {
        $cache = Craft::$app->getCache();
        $key = 'blockFieldCss.' . md5(implode('|', $this->cacheInputs(ThemeConfig::currentThemeHandle())));
        $payload = $cache->get($key);

        if (is_array($payload)) {
            return $payload;
        }

        $payload = [
            'css' => $this->generate(),
            'switchGroups' => $this->switchGroupMap(),
            'menuLabels' => $this->unofferedMenuLabels(),
            'unoffered' => $this->unofferedHandles(),
            'builderFields' => (new BlockRules('blockfields'))->resolve()['builderFields'],
            'nested' => $this->nestedRules(),
        ];

        $cache->set($key, $payload, self::CACHE_TTL);

        return $payload;
    }

    /**
     * @return string[]
     */
    private function cacheInputs(?string $themeHandle): array
    {
        $root = Craft::getAlias('@root');
        $paths = [
            __FILE__,
            (new \ReflectionClass(BlockRules::class))->getFileName(),
            "{$root}/config/stables/blockfields.php",
            "{$root}/config/blockfields.php",
        ];

        // Every level ThemeConfig reads, not just the active theme: `_base` holds Base's own rules, and a child theme
        // inherits its ancestors' rules and templates. Leaving any level out served that level's old rules for a day.
        $levels = ['_base'];

        if ($themeHandle !== null && preg_match('/^[a-z0-9\-]+$/', $themeHandle)) {
            $levels = ['_base', ...array_reverse((new ThemeRegistry())->chain($themeHandle))];
        }

        foreach ($levels as $level) {
            $paths[] = "{$root}/themes/{$level}/config/blockfields.json";
            $paths[] = "{$root}/themes/{$level}/config";
            $paths[] = "{$root}/themes/{$level}/templates/_blocks";
        }

        $inputs = [$themeHandle ?? '', Craft::$app->language, (string)Craft::$app->getProjectConfig()->get('dateModified')];

        foreach ($paths as $path) {
            $inputs[] = $path . ':' . (@filemtime($path) ?: 0);
        }

        return $inputs;
    }

    public function generate(): string
    {
        $resolved = (new BlockRules('blockfields'))->resolve();
        $this->logStale($resolved['stale']);

        $rules = [];

        foreach ($resolved['blocks'] as $handle => $block) {
            $rules = array_merge(
                $rules,
                $this->hiddenRules($handle, $block['type'], $block['own']['hidden'], null),
                $this->perLayoutRules($handle, $block['type'], $block['own']['perLayout'], null),
                $this->layoutOptionRules($block['layoutOptions']),
            );

            foreach ($block['children'] as $childHandle => $childRules) {
                $rules = array_merge(
                    $rules,
                    $this->hiddenRules($handle, $block['type'], $childRules['hidden'], $childHandle),
                    $this->perLayoutRules($handle, $block['type'], $childRules['perLayout'], $childHandle),
                );
            }
        }

        $rules = array_merge($rules, $this->lockedTypeRules($resolved['switchGroups']));

        if (!$rules) {
            return '';
        }

        $themeFile = ThemeConfig::appliedFile('blockfields');

        return '/* Generated by BlockFieldCss from config/stables/blockfields.php' . ($themeFile !== null ? " + {$themeFile}" : '') . " — do not edit. */\n"
            . implode("\n\n", $rules);
    }

    /**
     * Fields a block (or a type nested in it, when $childHandle is set) never shows.
     *
     * @param string[] $fieldHandles
     * @return string[]
     */
    private function hiddenRules(string $blockHandle, EntryType $blockType, array $fieldHandles, ?string $childHandle): array
    {
        if (!$fieldHandles) {
            return [];
        }

        $selectors = [];

        foreach ($fieldHandles as $fieldHandle) {
            foreach ($this->scopes($blockHandle, $blockType) as $scope) {
                $selectors[] = $scope['prefix'] . ' ' . $this->target($fieldHandle, $childHandle, $scope['inline'] ? $blockHandle : null);
            }
        }

        return [implode(",\n", $selectors) . " {\n    display: none;\n}"];
    }

    /**
     * Fields shown only on some of the block's layouts: hidden unless the block's own layout radio has one of the
     * allowed values checked. `:checked` is the live selection (Button Box renders a radio group), so it follows the
     * editor's clicks with no save. Written as "hide unless" so the field stays visible if the markup ever moves.
     *
     * @param array<string, array<string, string[]>> $layoutFieldMap layout field => field => allowed values
     * @return string[]
     */
    private function perLayoutRules(string $blockHandle, EntryType $blockType, array $layoutFieldMap, ?string $childHandle): array
    {
        $rules = [];

        foreach ($layoutFieldMap as $layoutHandle => $fieldMap) {
            foreach ($fieldMap as $fieldHandle => $values) {
                $checked = sprintf(
                    '[data-attribute="%s"] input:checked:is(%s)',
                    $this->escape($layoutHandle),
                    implode(', ', $this->valueSelectors($values))
                );
                $selectors = [];

                foreach ($this->scopes($blockHandle, $blockType) as $scope) {
                    $selectors[] = $scope['inline']
                        ? sprintf('%s:not(:has(%s)) %s', $scope['prefix'], $checked, $this->target($fieldHandle, $childHandle, $blockHandle))
                        : sprintf('%s:not(:has(.so-content %s)) %s', $scope['prefix'], $checked, $this->target($fieldHandle, $childHandle, null));
                }

                $rules[] = implode(",\n", $selectors) . " {\n    display: none;\n}";
            }
        }

        return $rules;
    }

    /**
     * Layout options the theme or site doesn't offer, hidden from the picker. A layout field belongs to one block
     * (BlockRules drops any other), so one unscoped selector covers every view mode. A saved value stays on its block.
     *
     * @param array<string, string[]> $layoutOptions
     * @return string[]
     */
    private function layoutOptionRules(array $layoutOptions): array
    {
        $rules = [];

        foreach ($layoutOptions as $layoutHandle => $values) {
            $rules[] = sprintf(
                "[data-attribute=\"%s\"] label.buttonbox-button:has(%s) {\n    display: none;\n}",
                $this->escape($layoutHandle),
                implode(', ', $this->valueSelectors($values))
            );
        }

        return $rules;
    }

    /**
     * Where a block's fields live: inline as its own .matrixblock (blocks view), or a slideout identified by one of its
     * layout tabs being a direct child of .so-content (cards view).
     *
     * @return array<int, array{prefix: string, inline: bool}>
     */
    private function scopes(string $blockHandle, EntryType $blockType): array
    {
        $scopes = [['prefix' => sprintf('.matrixblock[data-type="%s"]', $this->escape($blockHandle)), 'inline' => true]];

        foreach ($this->tabUids($blockType) as $tabUid) {
            $scopes[] = ['prefix' => sprintf('.cp-screen:has(.so-content > [data-layout-tab="%s"])', $this->escape($tabUid)), 'inline' => false];
        }

        return $scopes;
    }

    /**
     * The field wrapper at exactly one level. $inlineBlock is the block's handle when the scope is its inline
     * .matrixblock, whose own fields sit inside it; in a slideout the block's fields sit inside no .matrixblock.
     */
    private function target(string $fieldHandle, ?string $childHandle, ?string $inlineBlock): string
    {
        $field = sprintf('[data-attribute="%s"]', $this->escape($fieldHandle));

        if ($childHandle === null) {
            $outer = $inlineBlock !== null ? sprintf('.matrixblock[data-type="%s"] .matrixblock', $this->escape($inlineBlock)) : '.matrixblock';

            return sprintf('%s:not(%s %s)', $field, $outer, $field);
        }

        $child = sprintf('.matrixblock[data-type="%s"]', $this->escape($childHandle));
        $direct = $inlineBlock !== null
            ? sprintf('%s:not(.matrixblock[data-type="%s"] .matrixblock %s)', $child, $this->escape($inlineBlock), $child)
            : sprintf('%s:not(.matrixblock %s)', $child, $child);

        return sprintf('%s %s:not(%s .matrixblock %s)', $direct, $field, $child, $field);
    }

    /**
     * Attribute selectors matching an option's rendered input value, plain and base64 (BaseOptionsField::encodeValue).
     *
     * @param string[] $values
     * @return string[]
     */
    private function valueSelectors(array $values): array
    {
        $selectors = [];

        foreach ($values as $value) {
            $selectors[] = '[value="' . $this->escape($value) . '"]';
            // base64 is [A-Za-z0-9+/=], all safe inside a quoted selector.
            $selectors[] = '[value="base64:' . base64_encode($value) . '"]';
        }

        return $selectors;
    }

    /**
     * Hides the Entry Type field on nested blocks that aren't in a switch group at all. Filtering the dropdown only
     * protects grouped blocks; for the rest no switch is safe, so the control is removed. Nested block types only: a
     * section's entry editor is untouched.
     *
     * @param array<int, string[]> $groups
     * @return string[]
     */
    private function lockedTypeRules(array $groups): array
    {
        $grouped = [];
        foreach ($groups as $group) {
            foreach ($group as $handle) {
                $grouped[$handle] = true;
            }
        }

        $entries = Craft::$app->getEntries();
        $selectors = [];

        foreach ($this->nestedTypeHandles() as $handle) {
            if (isset($grouped[$handle])) {
                continue;
            }

            $type = $entries->getEntryTypeByHandle($handle);
            if (!$type) {
                continue;
            }

            foreach ($this->tabUids($type) as $tabUid) {
                $selectors[] = sprintf(
                    '.cp-screen:has(.so-content > [data-layout-tab="%s"]) .so-sidebar [data-attribute="typeId"]',
                    $this->escape($tabUid)
                );
            }
        }

        return $selectors
            ? [implode(",\n", $selectors) . " {\n    display: none;\n}"]
            : [];
    }

    /**
     * Every entry type that only ever exists inside a Matrix field.
     *
     * @return string[]
     */
    private function nestedTypeHandles(): array
    {
        $handles = [];

        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if (!$field instanceof \craft\fields\Matrix) {
                continue;
            }

            foreach ($field->getEntryTypes() as $type) {
                $handles[$type->handle] = true;
            }
        }

        return array_keys($handles);
    }

    /**
     * The switch groups as entry type id => allowed ids, for the JS that filters the type dropdown. JS because Garnish
     * appends an open disclosure menu to document.body, outside the form being edited.
     *
     * Every member keeps a key, even one the theme doesn't offer: the JS leaves a type without a key unfiltered. A block
     * the theme doesn't offer is left out of the other members' lists, but stays in its own.
     *
     * @return array<string, string[]>
     */
    public function switchGroupMap(): array
    {
        $entries = Craft::$app->getEntries();
        $unoffered = array_flip($this->unofferedHandles());
        $map = [];

        foreach ((new BlockRules('blockfields'))->resolve()['switchGroups'] as $group) {
            $types = [];

            foreach ($group as $handle) {
                if ($type = $entries->getEntryTypeByHandle((string)$handle)) {
                    $types[$type->handle] = (string)$type->id;
                }
            }

            // A group of one restricts nothing, and a group of none is a stale config line.
            if (count($types) < 2) {
                continue;
            }

            $offered = array_values(array_diff_key($types, $unoffered));

            foreach ($types as $handle => $id) {
                $map[$id] = array_values(array_unique([...$offered, $id]));
            }
        }

        return $map;
    }

    /**
     * Blocks this theme doesn't offer, by handle.
     *
     * @return string[]
     */
    public function unofferedHandles(): array
    {
        $handles = [];

        foreach ((new BlockRules('blockfields'))->resolve()['blocks'] as $handle => $block) {
            if (!$block['available']) {
                $handles[] = $handle;
            }
        }

        return $handles;
    }

    /**
     * Cards-view builder field handle => the Add menu labels of blocks this theme doesn't offer. Labels, because those
     * menu items carry no type id; built the way Craft builds them (Craft::t('site', name), name overrides included).
     * Two types sharing a label in one field are both skipped rather than risk hiding the wrong one.
     *
     * @return array<string, string[]>
     */
    public function unofferedMenuLabels(): array
    {
        $resolved = (new BlockRules('blockfields'))->resolve();
        $map = [];

        foreach ($resolved['builderFields'] as $fieldHandle) {
            $field = Craft::$app->getFields()->getFieldByHandle($fieldHandle);

            if (!$field instanceof \craft\fields\Matrix || $field->viewMode === \craft\fields\Matrix::VIEW_MODE_BLOCKS) {
                continue;
            }

            $labelCounts = [];
            $hidden = [];

            foreach ($field->getEntryTypes() as $type) {
                $label = Craft::t('site', $type->name);
                $labelCounts[$label] = ($labelCounts[$label] ?? 0) + 1;

                if (isset($resolved['blocks'][$type->handle]) && !$resolved['blocks'][$type->handle]['available']) {
                    $hidden[] = $label;
                }
            }

            foreach (array_unique($hidden) as $label) {
                if ($labelCounts[$label] > 1) {
                    $this->logStale(["{$fieldHandle}: more than one block is labelled \"{$label}\", so neither can be hidden from its Add menu"]);
                    continue;
                }

                $map[$fieldHandle][] = $label;
            }
        }

        return $map;
    }

    /**
     * @return string[]
     */
    /**
     * `nested` rules for the CP script (resources/js/blockNestedFields.js): the fields to hide on any block directly
     * inside each parent type, and how to recognise a parent open in a slideout — by its layout tabs' uids, the same
     * signal the generated CSS uses. Empty when no rule applies, so the script isn't loaded at all.
     *
     * @return array{rules: array<string, string[]>, slideoutTabs: array<string, string>}
     */
    public function nestedRules(): array
    {
        $rules = [];
        $slideoutTabs = [];

        foreach ((new BlockRules('blockfields'))->resolve()['nested'] as $parent => $nested) {
            $type = Craft::$app->getEntries()->getEntryTypeByHandle($parent);

            if ($type === null) {
                continue;
            }

            $rules[$parent] = $nested['hidden'];

            foreach ($this->tabUids($type) as $tabUid) {
                $slideoutTabs[$tabUid] = $parent;
            }
        }

        return ['rules' => $rules, 'slideoutTabs' => $slideoutTabs];
    }

    private function tabUids(EntryType $entryType): array
    {
        $layout = $entryType->getFieldLayout();

        return $layout ? array_map(fn($tab) => $tab->uid, $layout->getTabs()) : [];
    }

    /**
     * Logged once an hour per rule, not per CP request: a stale rule is left in place and ignored, never thrown.
     *
     * @param string[] $stale
     */
    private function logStale(array $stale): void
    {
        foreach ($stale as $message) {
            if (Craft::$app->getCache()->add('blockFieldCss.stale.' . md5($message), true, 3600)) {
                Craft::warning("Stale block rule, ignored: {$message}", __METHOD__);
            }
        }
    }

    /** Handles are [a-zA-Z0-9_]+ in Craft, but this is going into a selector. */
    private function escape(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $value);
    }
}
