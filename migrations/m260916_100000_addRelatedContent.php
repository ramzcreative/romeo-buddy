<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Entries;
use craft\fields\Matrix;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;

/**
 * Related content: a `relatedEntries` field on blog posts, events and jobs, and a `related` block in every builder
 * field. See docs/business-content-spec.md §5 and config/stables/related.php.
 *
 * Adopt-if-present throughout: project config applies before content migrations, so any of it may already exist.
 * Fields go into existing layouts in place (content is keyed by layout element uid).
 */
class m260916_100000_addRelatedContent extends Migration
{
    private const FIELD = 'relatedEntries';
    private const BLOCK = 'related';

    /** Entry type handle => the tab the field goes at the end of. */
    private const ON = ['blogPost' => 'Content', 'event' => 'Settings', 'job' => 'Settings'];

    /** Builder field => its group for the block, when it groups. */
    private const BUILDERS = ['pageBuilder' => 'Components', 'postBuilder' => 'General', 'containerBlocks' => null, 'columnBuilder' => null];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle(self::FIELD);

        if ($field === null) {
            $field = new Entries([
                'name' => 'Related',
                'handle' => self::FIELD,
                'instructions' => 'Your picks show first. Any space left fills with related entries automatically.',
                'sources' => $this->sources(),
                'maxRelations' => 6,
                'viewMode' => 'cards',
            ]);

            if (!$fields->saveField($field)) {
                throw new \Exception("Couldn't save the '" . self::FIELD . "' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        foreach (self::ON as $entryTypeHandle => $tabName) {
            $this->appendToTab($entryTypeHandle, $tabName, $field);
        }

        $block = Craft::$app->getEntries()->getEntryTypeByHandle(self::BLOCK) ?? $this->createBlock($field);

        foreach (self::BUILDERS as $builderHandle => $group) {
            $this->offer($builderHandle, $block, $group);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $block = $entries->getEntryTypeByHandle(self::BLOCK);

        if ($block !== null) {
            foreach (array_keys(self::BUILDERS) as $builderHandle) {
                $builder = $fields->getFieldByHandle($builderHandle);

                if ($builder instanceof Matrix) {
                    $builder->setEntryTypes(array_values(array_filter($builder->getEntryTypes(), static fn(EntryType $t): bool => $t->handle !== self::BLOCK)));
                    $fields->saveField($builder);
                }
            }

            $entries->deleteEntryType($block);
        }

        // A null match must never reach a delete: see the stables CLAUDE.md on the migration that wiped a production site.
        $field = $fields->getFieldByHandle(self::FIELD);

        if ($field instanceof Entries) {
            foreach (array_keys(self::ON) as $entryTypeHandle) {
                $this->removeFrom($entryTypeHandle);
            }

            $fields->deleteField($field);
        }

        return true;
    }

    /** @return string[] */
    private function sources(): array
    {
        $config = \modules\support\Config::get('related');
        $sources = [];

        foreach ((array)($config['sections'] ?? ['blog', 'events', 'jobs', 'pages']) as $handle) {
            if ($section = Craft::$app->getEntries()->getSectionByHandle((string)$handle)) {
                $sources[] = 'section:' . $section->uid;
            }
        }

        return $sources;
    }

    private function createBlock(Entries $field): EntryType
    {
        $content = new FieldLayoutTab(['name' => 'Content']);
        $settings = new FieldLayoutTab(['name' => 'Settings']);
        $layout = new FieldLayout(['type' => Entry::class]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs([$content, $settings]);
        $content->setElements([new CustomField($field)]);

        if ($blockHeading = Craft::$app->getFields()->getFieldByHandle('blockHeading')) {
            $settings->setElements([new CustomField($blockHeading)]);
        }

        $entryType = new EntryType([
            'name' => 'Related',
            'handle' => self::BLOCK,
            'hasTitleField' => false,
            'showSlugField' => false,
            'showStatusField' => true,
            'icon' => 'link',
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Related' block: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    private function offer(string $builderHandle, EntryType $block, ?string $group): void
    {
        $fields = Craft::$app->getFields();
        $builder = $fields->getFieldByHandle($builderHandle);

        if (!$builder instanceof Matrix) {
            return;
        }

        $existing = $builder->getEntryTypes();

        foreach ($existing as $type) {
            if ($type->handle === self::BLOCK) {
                return;
            }
        }

        $groups = array_values(array_unique(array_filter(array_map(static fn(EntryType $t): ?string => $t->group, $existing))));
        $usage = clone $block;
        $usage->original = $block;
        $usage->group = $groups === [] ? null : ($group !== null && in_array($group, $groups, true) ? $group : $groups[0]);

        $builder->setEntryTypes([...$existing, $usage]);

        if (!$fields->saveField($builder)) {
            throw new \Exception("Couldn't offer the Related block in '{$builderHandle}': " . implode(', ', $builder->getErrorSummary(true)));
        }
    }

    /**
     * Appends the field to the end of the named tab (the first tab if there's none by that name), keeping every
     * existing element and its uid.
     */
    private function appendToTab(string $entryTypeHandle, string $tabName, Entries $field): void
    {
        $entries = Craft::$app->getEntries();
        $entryType = $entries->getEntryTypeByHandle($entryTypeHandle);

        if ($entryType === null) {
            return;
        }

        $layout = $entryType->getFieldLayout();

        foreach ($layout->getCustomFieldElements() as $element) {
            if ($this->handleOf($element) === self::FIELD) {
                return;
            }
        }

        $tabs = $layout->getTabs();
        $target = $tabs[array_key_first($tabs)];

        foreach ($tabs as $tab) {
            if ($tab->name === $tabName) {
                $target = $tab;
                break;
            }
        }

        $uidsBefore = array_map(fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        $hadTitleField = $entryType->hasTitleField;

        $layout->setTabs($tabs);
        $target->setElements([...array_values($target->getElements()), new CustomField($field)]);
        $entryType->setFieldLayout($layout);

        if (!$entries->saveEntryType($entryType)) {
            throw new \Exception("Couldn't add " . self::FIELD . " to '{$entryTypeHandle}': " . implode(', ', $entryType->getErrorSummary(true)));
        }

        $saved = $entries->getEntryTypeById($entryType->id);
        $uidsAfter = array_map(fn(CustomField $el) => $el->uid, $saved->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== [] || $saved->hasTitleField !== $hadTitleField) {
            throw new \Exception("Adding " . self::FIELD . " to '{$entryTypeHandle}' changed its existing layout; stopped.");
        }
    }

    private function removeFrom(string $entryTypeHandle): void
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
            $kept = array_values(array_filter($tab->getElements(), fn(mixed $el): bool => $this->handleOf($el) !== self::FIELD));

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
