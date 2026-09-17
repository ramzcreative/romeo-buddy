<?php

namespace modules\stablestwigextensions\services;

use Craft;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\models\Section;
use RuntimeException;

/**
 * Small content-model changes shared by migrations and the `content` console commands: adding a field to an existing
 * layout in place (content is keyed by layout element uid, so a layout is never rebuilt) and showing or hiding a
 * section in the Entries sidebar.
 */
class ContentModel
{
    /**
     * Adds a field to an entry type's layout, after `$afterHandle` when given (in that field's tab), otherwise at the
     * end of the tab named `$tabName` (the first tab if there's none by that name). Returns false when it's already
     * there. Throws when saving would change any existing element's uid.
     */
    public function addField(string $entryTypeHandle, FieldInterface $field, string $tabName, ?string $afterHandle = null): bool
    {
        $entries = Craft::$app->getEntries();
        $entryType = $entries->getEntryTypeByHandle($entryTypeHandle) ?? throw new RuntimeException("No entry type '{$entryTypeHandle}'.");
        $layout = $entryType->getFieldLayout();

        foreach ($layout->getCustomFieldElements() as $element) {
            if ($this->handleOf($element) === $field->handle) {
                return false;
            }
        }

        $tabs = $layout->getTabs();
        $target = $tabs[array_key_first($tabs)];
        $insertAt = null;

        foreach ($tabs as $tab) {
            if ($tab->name === $tabName) {
                $target = $tab;
                break;
            }
        }

        if ($afterHandle !== null) {
            foreach ($tabs as $tab) {
                foreach (array_values($tab->getElements()) as $i => $element) {
                    if ($this->handleOf($element) === $afterHandle) {
                        $target = $tab;
                        $insertAt = $i + 1;
                        break 2;
                    }
                }
            }
        }

        $uidsBefore = array_map(fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        $hadTitleField = $entryType->hasTitleField;

        $elements = array_values($target->getElements());
        array_splice($elements, $insertAt ?? count($elements), 0, [new CustomField($field)]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);
        $target->setElements($elements);
        $entryType->setFieldLayout($layout);

        if (!$entries->saveEntryType($entryType)) {
            throw new RuntimeException("Couldn't add {$field->handle} to '{$entryTypeHandle}': " . implode(', ', $entryType->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($entryType->id);
        $uidsAfter = array_map(fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== [] || $saved->hasTitleField !== $hadTitleField) {
            throw new RuntimeException("Adding {$field->handle} to '{$entryTypeHandle}' changed its existing layout; stopped.");
        }

        return true;
    }

    public function removeField(string $entryTypeHandle, string $fieldHandle): void
    {
        $entries = Craft::$app->getEntries();
        $entryType = $entries->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            return;
        }

        $layout = $entryType->getFieldLayout();
        $tabs = $layout->getTabs();
        $changed = false;

        foreach ($tabs as $tab) {
            $kept = array_values(array_filter($tab->getElements(), fn(mixed $el): bool => $this->handleOf($el) !== $fieldHandle));

            if (count($kept) !== count($tab->getElements())) {
                $changed = true;
                $layout->setTabs($tabs);
                $tab->setElements($kept);
            }
        }

        if ($changed) {
            $entryType->setFieldLayout($layout);
            $entries->saveEntryType($entryType);
        }
    }

    /**
     * Shows or hides a section in the Entries sidebar ("Customize sources" → Enabled). A section with no saved source
     * config is added after `$afterSectionHandle` when given, otherwise at the end. Returns whether anything changed.
     */
    public function setSourceEnabled(Section $section, bool $enabled, ?string $afterSectionHandle = null): bool
    {
        $elementSources = Craft::$app->getElementSources();
        $key = 'section:' . $section->uid;
        $sources = Craft::$app->getProjectConfig()->get('elementSources.' . Entry::class) ?? [];

        foreach ($sources as $i => $source) {
            if (($source['key'] ?? null) === $key) {
                if (($source['disabled'] ?? false) === !$enabled) {
                    return false;
                }

                $sources[$i]['disabled'] = !$enabled;
                $elementSources->saveSources(Entry::class, $sources);

                return true;
            }
        }

        $new = ['type' => 'native', 'key' => $key, 'page' => 'Entries', 'disabled' => !$enabled];
        $insertAt = count($sources);

        if ($afterSectionHandle !== null && ($after = Craft::$app->getEntries()->getSectionByHandle($afterSectionHandle))) {
            foreach ($sources as $i => $source) {
                if (($source['key'] ?? null) === 'section:' . $after->uid) {
                    $insertAt = $i + 1;
                    $new['page'] = $source['page'] ?? 'Entries';
                    break;
                }
            }
        }

        array_splice($sources, $insertAt, 0, [$new]);
        $elementSources->saveSources(Entry::class, $sources);

        return true;
    }

    /** Drops a section's saved source config, before the section is deleted. */
    public function removeSource(Section $section): void
    {
        $key = 'section:' . $section->uid;
        $sources = Craft::$app->getProjectConfig()->get('elementSources.' . Entry::class) ?? [];
        $kept = array_values(array_filter($sources, static fn(array $source): bool => ($source['key'] ?? null) !== $key));

        if (count($kept) !== count($sources)) {
            Craft::$app->getElementSources()->saveSources(Entry::class, $kept);
        }
    }

    public function isSourceEnabled(Section $section): bool
    {
        foreach (Craft::$app->getProjectConfig()->get('elementSources.' . Entry::class) ?? [] as $source) {
            if (($source['key'] ?? null) === 'section:' . $section->uid) {
                return !($source['disabled'] ?? false);
            }
        }

        return true;
    }

    private function handleOf(mixed $element): ?string
    {
        if (!$element instanceof CustomField) {
            return null;
        }

        try {
            return $element->getField()->handle;
        } catch (\Throwable) {
            return null;
        }
    }
}
