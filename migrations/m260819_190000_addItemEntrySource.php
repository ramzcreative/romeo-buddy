<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\elements\conditions\entries\EntryCondition;
use craft\fieldlayoutelements\CustomField;
use craft\fields\conditions\LightswitchFieldConditionRule;
use craft\fields\Entries as EntriesField;
use craft\fields\Lightswitch;

/**
 * Lets a page-builder item pull its content from a selected entry, with the
 * item's own values winning field by field.
 *
 * Two fields on the shared `item` type: a toggle, and the entry picker it
 * reveals. Which of this site's fields feed which key is config/items.php,
 * not code — see ItemResolver.
 *
 * The picker's visibility IS a native Craft conditional field, unlike the
 * per-block field hiding in config/blockfields.php which has to be CSS. The
 * difference is what the condition depends on: this one is item-local (a
 * toggle on the same element), so Craft can evaluate it. Per-block visibility
 * depends on which block owns the item, and Craft ships no owner-aware rule.
 *
 * Purely additive — it adds two fields and touches nothing that exists. That
 * matters because `php craft up` applies project config BEFORE content
 * migrations, so on production the fields arrive via project config first and
 * this migration finds them already in place and skips. Both orders reach the
 * same result, which is the property a migration needs to have and the one
 * that was missing when this repo's last field change deleted a site.
 */
class m260819_190000_addItemEntrySource extends Migration
{
    private const ENTRY_TYPE = 'item';

    private const FIELDS = [
        'useEntry' => [
            'class' => Lightswitch::class,
            'name' => 'Pull From an Entry',
            'instructions' => 'On to base this item on existing content instead of retyping it. Anything you fill in below still wins over what the entry provides.',
        ],
        'sourceEntry' => [
            'class' => EntriesField::class,
            'name' => 'Source Entry',
            'instructions' => 'The entry to pull the heading, image and intro from.',
        ],
    ];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        foreach (self::FIELDS as $handle => $def) {
            if ($fields->getFieldByHandle($handle)) {
                continue;
            }

            $config = [
                'name' => $def['name'],
                'handle' => $handle,
                'instructions' => $def['instructions'],
            ];

            if ($def['class'] === EntriesField::class) {
                $config['maxRelations'] = 1;
                $config['selectionLabel'] = 'Choose an entry';
            }

            $field = new $def['class']($config);

            if (!$fields->saveField($field)) {
                throw new \Exception("Couldn't save '$handle': " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        $entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE);
        if (!$entryType) {
            throw new \Exception("The '" . self::ENTRY_TYPE . "' entry type is missing.");
        }

        $layout = $entryType->getFieldLayout();
        $tab = $layout->getTabs()[0] ?? null;
        if (!$tab) {
            throw new \Exception('The item field layout has no tabs.');
        }

        $existing = [];
        foreach ($tab->getElements() as $el) {
            if ($el instanceof CustomField && $el->getField()) {
                $existing[$el->getField()->handle] = true;
            }
        }

        // Already on the layout — project config got here first, or this has
        // run before. Either way there is nothing to do.
        if (isset($existing['useEntry'], $existing['sourceEntry'])) {
            return true;
        }

        $useEntry = $fields->getFieldByHandle('useEntry');
        $sourceEntry = $fields->getFieldByHandle('sourceEntry');

        // Both lead the tab: they decide where everything below comes from, so
        // they belong above it rather than appended at the end.
        $lead = [];

        if (!isset($existing['useEntry']) && $useEntry) {
            $lead[] = new CustomField($useEntry);
        }

        if (!isset($existing['sourceEntry']) && $sourceEntry) {
            $el = new CustomField($sourceEntry);
            $el->setElementCondition($this->lightswitchIs($useEntry->uid, true));
            $lead[] = $el;
        }

        // setTabs() before setElements() — a tab that isn't attached to its
        // layout yet throws "Field layout tab is missing its field layout."
        $layout->setTabs($layout->getTabs());
        $tab->setElements(array_merge($lead, $tab->getElements()));
        $entryType->setFieldLayout($layout);

        if (!$entries->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the item entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return true;
    }

    private function lightswitchIs(string $fieldUid, bool $value): EntryCondition
    {
        $condition = new EntryCondition();
        $condition->elementType = Entry::class;
        $condition->setConditionRules([
            [
                'class' => LightswitchFieldConditionRule::class,
                'fieldUid' => $fieldUid,
                'value' => $value,
            ],
        ]);

        return $condition;
    }

    public function safeDown(): bool
    {
        $fields = Craft::$app->getFields();

        foreach (array_keys(self::FIELDS) as $handle) {
            if ($field = $fields->getFieldByHandle($handle)) {
                $fields->deleteField($field);
            }
        }

        return true;
    }
}
