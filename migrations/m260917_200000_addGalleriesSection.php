<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Entries;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * Galleries, phase 1 (business blocks spec §5.2): a gallery becomes an entry that pages pick, instead of images set
 * again on every block. Adds only, deletes nothing:
 * - a `galleries` section (channel, no URLs) of `galleriesCollection` entries, each with `galleryImages`;
 * - `galleryEntry` (pick one gallery) beside `galleryImages` on the `gallery` block and on `project`;
 * - for every gallery block and project that has images and no gallery picked, a gallery entry with the same images in
 *   the same order, picked. Identical image lists share one gallery entry. Drafts are copied too; revisions aren't.
 *
 * Images are read from the relations table, not the field, so this works whether or not project config has already
 * taken `galleryImages` off those layouts. Every copy is re-read and compared before the migration finishes.
 * Phase 2 (m260917_210000) takes `galleryImages` off the block and `project`, in a later deploy.
 */
class m260917_200000_addGalleriesSection extends Migration
{
    private const SECTION = 'galleries';
    private const ENTRY_TYPE = 'galleriesCollection';
    private const SOURCE_TYPES = ['gallery', 'project'];

    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();
        $images = $fields->getFieldByHandle('galleryImages');

        if ($images === null) {
            throw new \Exception("No 'galleryImages' field; the gallery block migration (m260916_120000) must be applied first.");
        }

        $entryType = $entries->getEntryTypeByHandle(self::ENTRY_TYPE) ?? $this->createEntryType($images);
        $section = $this->section($entryType);

        $picker = $fields->getFieldByHandle('galleryEntry') ?? new Entries([
            'name' => 'Gallery',
            'handle' => 'galleryEntry',
            'instructions' => 'Choose a gallery from Galleries. Changing its images there changes them everywhere it’s used.',
            'sources' => ['section:' . $section->uid],
            'maxRelations' => 1,
            'selectionLabel' => 'Choose a gallery',
        ]);

        if (!$picker->id && !$fields->saveField($picker)) {
            throw new \Exception("Couldn't save the 'galleryEntry' field: " . implode(', ', $picker->getErrorSummary(true)));
        }

        foreach (self::SOURCE_TYPES as $typeHandle) {
            $this->attach($typeHandle, $picker);
        }

        $this->copy($images, $picker, $section);

        return true;
    }

    /**
     * Phase 2 runs this again, for anything given images between the two deploys.
     */
    public function catchUp(): void
    {
        $fields = Craft::$app->getFields();
        $images = $fields->getFieldByHandle('galleryImages');
        $picker = $fields->getFieldByHandle('galleryEntry');
        $section = Craft::$app->getEntries()->getSectionByHandle(self::SECTION);

        if ($images === null || $picker === null || $section === null) {
            throw new \Exception('Galleries phase 1 (m260917_200000_addGalleriesSection) must be applied first.');
        }

        $this->copy($images, $picker, $section);
    }

    public function safeDown(): bool
    {
        echo "    > m260917_200000_addGalleriesSection created content; restore a backup to undo it.\n";

        return false;
    }

    private function createEntryType(\craft\base\FieldInterface $images): EntryType
    {
        $content = new FieldLayoutTab(['name' => 'Content']);
        $layout = new FieldLayout(['type' => Entry::class]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs([$content]);
        $content->setElements([
            new \craft\fieldlayoutelements\entries\EntryTitleField(['required' => true]),
            new CustomField($images, ['label' => 'Images']),
        ]);

        $entryType = new EntryType([
            'name' => 'Gallery',
            'handle' => self::ENTRY_TYPE,
            'hasTitleField' => true,
            'titleTranslationMethod' => 'none',
            'showSlugField' => false,
            'showStatusField' => true,
            'icon' => 'images',
        ]);
        $entryType->setFieldLayout($layout);

        if (!Craft::$app->getEntries()->saveEntryType($entryType)) {
            throw new \Exception("Couldn't save the 'Gallery' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
        }

        return $entryType;
    }

    private function section(EntryType $entryType): Section
    {
        $entries = Craft::$app->getEntries();

        if ($section = $entries->getSectionByHandle(self::SECTION)) {
            return $section;
        }

        $section = new Section([
            'name' => 'Galleries',
            'handle' => self::SECTION,
            'type' => Section::TYPE_CHANNEL,
            'enableVersioning' => true,
            'propagationMethod' => PropagationMethod::All,
        ]);

        $siteSettings = [];

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[$site->id] = new Section_SiteSettings([
                'siteId' => $site->id,
                'enabledByDefault' => true,
                'hasUrls' => false,
                'uriFormat' => null,
                'template' => null,
            ]);
        }

        $section->setSiteSettings($siteSettings);
        $section->setEntryTypes([$entryType]);

        if (!$entries->saveSection($section)) {
            throw new \Exception("Couldn't save the 'Galleries' section: " . implode(', ', $section->getErrorSummary(true)));
        }

        return $section;
    }

    /**
     * Straight after `galleryImages` when the layout still has it, otherwise at the end of the first tab.
     */
    private function attach(string $typeHandle, \craft\base\FieldInterface $picker): void
    {
        $entries = Craft::$app->getEntries();
        $type = $entries->getEntryTypeByHandle($typeHandle);

        if ($type === null) {
            echo "    > No '{$typeHandle}' entry type on this site — skipped.\n";

            return;
        }

        $layout = $type->getFieldLayout();

        if ($layout->getFieldByHandle('galleryEntry')) {
            return;
        }

        $tabs = $layout->getTabs();
        $target = $tabs[array_key_first($tabs)];
        $insertAt = count($target->getElements());

        foreach ($tabs as $tab) {
            foreach (array_values($tab->getElements()) as $i => $element) {
                if ($element instanceof CustomField && $element->getField()->handle === 'galleryImages') {
                    $target = $tab;
                    $insertAt = $i + 1;
                    break 2;
                }
            }
        }

        $elements = array_values($target->getElements());
        $uidsBefore = array_map(static fn(CustomField $el) => $el->uid, $layout->getCustomFieldElements());
        array_splice($elements, $insertAt, 0, [new CustomField($picker)]);

        // setElements() only after setTabs(): see the stables CLAUDE.md.
        $layout->setTabs($tabs);
        $target->setElements($elements);
        $type->setFieldLayout($layout);

        if (!$entries->saveEntryType($type)) {
            throw new \Exception("Couldn't add galleryEntry to '{$typeHandle}': " . implode(', ', $type->getErrorSummary(true)));
        }

        $uidsAfter = array_map(static fn(CustomField $el) => $el->uid, $entries->getEntryTypeById($type->id)->getFieldLayout()->getCustomFieldElements());

        if (array_diff($uidsBefore, $uidsAfter) !== []) {
            throw new \Exception("Adding galleryEntry to '{$typeHandle}' changed its existing layout; stopped.");
        }

        echo "    > Added galleryEntry to '{$typeHandle}'.\n";
    }

    private function copy(\craft\base\FieldInterface $images, \craft\base\FieldInterface $picker, Section $section): void
    {
        $typeIds = array_values(array_filter(array_map(
            static fn(string $handle) => Craft::$app->getEntries()->getEntryTypeByHandle($handle)?->id,
            self::SOURCE_TYPES
        )));

        if ($typeIds === []) {
            return;
        }

        // Snapshot first: which sources already have a gallery picked must not change while this runs.
        $sourceIds = (new Query())
            ->select(['e.id'])
            ->from(['e' => Table::ENTRIES])
            ->innerJoin(['el' => Table::ELEMENTS], '[[el.id]] = [[e.id]]')
            ->where(['e.typeId' => $typeIds, 'el.revisionId' => null, 'el.dateDeleted' => null])
            ->column();

        if ($sourceIds === []) {
            return;
        }

        $picked = array_flip((new Query())->select(['sourceId'])->distinct()->from(Table::RELATIONS)
            ->where(['fieldId' => $picker->id, 'sourceId' => $sourceIds])->column());

        $rows = (new Query())
            ->select(['sourceId', 'targetId'])
            ->from(Table::RELATIONS)
            ->where(['fieldId' => $images->id, 'sourceId' => $sourceIds])
            ->orderBy(['sourceId' => SORT_ASC, 'sortOrder' => SORT_ASC])
            ->all();

        /** @var array<int, int[]> $imagesBySource */
        $imagesBySource = [];

        foreach ($rows as $row) {
            if (!isset($picked[(int)$row['sourceId']])) {
                $imagesBySource[(int)$row['sourceId']][] = (int)$row['targetId'];
            }
        }

        $gallerySignatures = [];
        $made = 0;

        foreach ($imagesBySource as $sourceId => $imageIds) {
            $source = Entry::find()->id($sourceId)->status(null)->drafts(null)->provisionalDrafts(null)->site('*')->unique()->one();

            if ($source === null) {
                echo "    > Entry {$sourceId} has gallery images but couldn't be loaded; skipped.\n";
                continue;
            }

            $signature = implode(',', $imageIds);

            if (!isset($gallerySignatures[$signature])) {
                $gallery = new Entry([
                    'sectionId' => $section->id,
                    'typeId' => $section->getEntryTypes()[0]->id,
                    'title' => $this->titleFor($source, Entry::find()->sectionId($section->id)->status(null)->count() + 1),
                ]);
                $gallery->setFieldValue('galleryImages', $imageIds);

                if (!Craft::$app->getElements()->saveElement($gallery, false)) {
                    throw new \Exception("Couldn't create a gallery for entry {$sourceId}: " . implode(', ', $gallery->getErrorSummary(true)));
                }

                $gallerySignatures[$signature] = $gallery->id;
                $made++;
            }

            $source->setFieldValue('galleryEntry', [$gallerySignatures[$signature]]);

            if (!Craft::$app->getElements()->saveElement($source, false, false, false)) {
                throw new \Exception("Couldn't pick the gallery on entry {$sourceId}: " . implode(', ', $source->getErrorSummary(true)));
            }
        }

        $this->assertCopied($imagesBySource, $picker, $images);

        echo "    > Created {$made} gallery entr" . ($made === 1 ? 'y' : 'ies') . ' for ' . count($imagesBySource) . " block(s) and project(s).\n";
    }

    private function titleFor(Entry $source, int $n): string
    {
        if ($source->getType()->handle === 'project') {
            return trim((string)$source->title) ?: "Gallery {$n}";
        }

        $heading = $source->getFieldLayout()?->getFieldByHandle('blockHeading') ? $source->getFieldValue('blockHeading')->one() : null;
        $text = $heading ? trim(strip_tags((string)($heading->getFieldValue('heading') ?? ''))) : '';

        return $text !== '' ? $text : "Gallery {$n}";
    }

    /**
     * @param array<int, int[]> $imagesBySource
     */
    private function assertCopied(array $imagesBySource, \craft\base\FieldInterface $picker, \craft\base\FieldInterface $images): void
    {
        foreach ($imagesBySource as $sourceId => $imageIds) {
            $galleryId = (new Query())->select(['targetId'])->from(Table::RELATIONS)
                ->where(['fieldId' => $picker->id, 'sourceId' => $sourceId])->scalar();

            $copied = $galleryId ? array_map('intval', (new Query())->select(['targetId'])->from(Table::RELATIONS)
                ->where(['fieldId' => $images->id, 'sourceId' => (int)$galleryId])->orderBy(['sortOrder' => SORT_ASC])->column()) : [];

            if ($copied !== $imageIds) {
                throw new \Exception("Entry {$sourceId}'s gallery doesn't match its images after copying; nothing was kept.");
            }
        }
    }
}
