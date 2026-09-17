<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;

/**
 * Checks — and changes nothing — that every `imageItem` left on this site already has a matching `item` on the same
 * block, from this site's own phase 1 (`moveBlockContentIntoItems`). The boilerplate's consolidation migration would
 * re-type them instead, which here would leave each imageText block holding the item twice, so this site retires them
 * with a delete-only step (stables docs/romeo-buddy-port-plan.md, P3/P4) and this is the guard that step relies on.
 *
 * Matching is by owner and by content: an item on the same block whose image and text match the imageItem's. A miss
 * throws, so the phase that deletes can't run against content it hasn't accounted for.
 */
class m260917_240000_checkImageItemsCopied extends Migration
{
    public function safeUp(): bool
    {
        $type = Craft::$app->getEntries()->getEntryTypeByHandle('imageItem');

        if ($type === null) {
            echo "  > No 'imageItem' entry type on this site — nothing to check.\n";

            return true;
        }

        $imageItems = Entry::find()->type('imageItem')->status(null)->drafts(null)->provisionalDrafts(null)->site('*')->unique()->all();

        if ($imageItems === []) {
            echo "  > No imageItem entries left — nothing to check.\n";

            return true;
        }

        $missing = [];

        foreach ($imageItems as $imageItem) {
            $ownerId = $imageItem->getPrimaryOwnerId();
            $image = $imageItem->getFieldLayout()?->getFieldByHandle('image') ? $imageItem->getFieldValue('image')->ids() : [];
            $text = $this->textOf($imageItem);
            $matched = false;

            foreach (Entry::find()->type('item')->status(null)->drafts(null)->provisionalDrafts(null)->site('*')->unique()->all() as $item) {
                if ($item->getPrimaryOwnerId() !== $ownerId) {
                    continue;
                }

                $itemImage = $item->getFieldLayout()?->getFieldByHandle('image') ? $item->getFieldValue('image')->ids() : [];

                if ($itemImage === $image && $this->textOf($item) === $text) {
                    $matched = true;
                    echo "  > imageItem {$imageItem->id} (owner {$ownerId}) is already item {$item->id}.\n";
                    break;
                }
            }

            if (!$matched) {
                $missing[] = $imageItem->id . ' (owner ' . $ownerId . ')';
            }
        }

        if ($missing !== []) {
            throw new \Exception('These imageItems have no matching item and would be lost by the retire step: ' . implode(', ', $missing));
        }

        echo '  > All ' . count($imageItems) . " imageItem(s) already copied; safe to retire.\n";

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }

    private function textOf(Entry $entry): string
    {
        foreach (['text', 'intro', 'heading'] as $handle) {
            if ($entry->getFieldLayout()?->getFieldByHandle($handle)) {
                $value = trim(strip_tags((string)$entry->getFieldValue($handle)));

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}
