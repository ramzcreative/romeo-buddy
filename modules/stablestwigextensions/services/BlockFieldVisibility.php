<?php

namespace modules\stablestwigextensions\services;

use craft\elements\Entry;
use craft\models\EntryType;
use modules\themepicker\services\BlockRules;

/**
 * Whether one of a block's own fields is currently visible, per the SAME
 * rules craft-modules' BlockRules resolves for the CP (config/stables/
 * blockfields.php + the active theme's config/blockfields.json) — see
 * BlockFieldCss, the CP's own consumer of BlockRules::resolve(). This is a
 * second consumer of that one resolved ruleset, not a second copy of it:
 * the gear panel (inline editing's front-end settings popover) must never
 * offer, and the save endpoint must never accept, a field the active
 * theme currently hides from that block in the CP.
 */
class BlockFieldVisibility
{
    /**
     * @param array<string, string> $candidateLayoutValues layout field
     *        handle => a not-yet-saved value to check against instead of
     *        the block's own current one — lets the gear panel ask "if
     *        this layout were picked instead, would this field still
     *        show?" without writing anything.
     */
    public function isVisible(EntryType $blockType, ?Entry $block, string $fieldHandle, array $candidateLayoutValues = []): bool
    {
        $rules = (new BlockRules('blockfields'))->resolve()['blocks'][$blockType->handle]['own'] ?? null;

        if ($rules === null) {
            return true;
        }

        if (in_array($fieldHandle, $rules['hidden'], true)) {
            return false;
        }

        foreach ($rules['perLayout'] as $layoutHandle => $fieldMap) {
            if (!isset($fieldMap[$fieldHandle])) {
                continue;
            }

            $current = $candidateLayoutValues[$layoutHandle]
                ?? (string)($block?->getFieldValue($layoutHandle) ?? '');

            if (!in_array($current, $fieldMap[$fieldHandle], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Option values a theme withholds from a layout field's own picker
     * (e.g. `layoutCards` with `large` turned off) — Craft's own field
     * validation only knows the field's configured options, not which of
     * them the active theme currently offers, so this needs checking
     * separately wherever a layout value is accepted.
     *
     * @return string[]
     */
    public function hiddenLayoutOptionValues(EntryType $blockType, string $layoutFieldHandle): array
    {
        return (new BlockRules('blockfields'))->resolve()['blocks'][$blockType->handle]['layoutOptions'][$layoutFieldHandle] ?? [];
    }
}
