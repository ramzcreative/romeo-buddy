<?php

namespace modules\stablestwigextensions\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\fields\Entries;
use modules\stablestwigextensions\services\ContentModel;
use RuntimeException;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Turns on a section that ships off (Articles, Projects) and attaches topics to other entry types. Idempotent: each
 * step says whether it did something or found it done. See docs/business-content-spec.md §6.1.
 */
class ContentController extends Controller
{
    private const OPTIONAL = [
        'articles' => ['type' => 'article', 'attach' => [['postAuthors', 'Content', 'image'], ['relatedEntries', 'Settings', null], ['topics', 'Settings', null]], 'schemaType' => 'Article'],
        'projects' => ['type' => 'project', 'attach' => [['relatedEntries', 'Settings', null], ['topics', 'Settings', null]], 'schemaType' => null],
    ];

    /** For `enable`: the section's new name, e.g. "Work". The handle stays. */
    public ?string $name = null;

    /** For `enable`: the entry type's new name. Defaults to --name. */
    public ?string $typeName = null;

    /** For `enable`: the URI prefix, e.g. "work" for work/{slug}; also the index page's slug. */
    public ?string $uri = null;

    public function options($actionID): array
    {
        $options = parent::options($actionID);

        return $actionID === 'enable' ? [...$options, 'name', 'typeName', 'uri'] : $options;
    }

    /**
     * Usage: php craft stablestwigextensions/content/enable <articles|projects> [--name="Work"] [--type-name="Project"] [--uri=work]
     */
    public function actionEnable(string $handle): int
    {
        $spec = self::OPTIONAL[$handle] ?? null;
        $entries = Craft::$app->getEntries();
        $section = $entries->getSectionByHandle($handle);

        if ($spec === null) {
            $this->stderr("\"{$handle}\" isn't a section that ships off. Use articles or projects.\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        if ($section === null || $entries->getEntryTypeByHandle($spec['type']) === null) {
            $this->stderr("The {$handle} section doesn't exist here. Run php craft up first.\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($this->uri !== null && !preg_match('/^[a-z0-9\-]+(\/[a-z0-9\-]+)*$/', $this->uri)) {
            $this->stderr("--uri must be a path like \"work\" or \"our-work\".\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        $model = new ContentModel();
        $fields = Craft::$app->getFields();

        try {
            // 1. Show it in Entries.
            $this->step('Entries sidebar', $model->setSourceEnabled($section, true) ? 'shown' : null);

            // 2. Relation fields, in place.
            foreach ($spec['attach'] as [$fieldHandle, $tab, $after]) {
                $field = $fields->getFieldByHandle($fieldHandle);

                if ($field === null) {
                    $this->step("{$fieldHandle} field", false, "not on this site; skipped");
                    continue;
                }

                $this->step("{$fieldHandle} on {$spec['type']}", $model->addField($spec['type'], $field, $tab, $after) ? 'added' : null);
            }

            // 3. Rename, keeping handles.
            $this->rename($section, $spec['type']);

            // 4. Offered by the posts block.
            $this->step('postType option', $this->addPostTypeOption($handle, $section->name) ? 'added' : null);

            // 5. A disabled index page to review and publish.
            $slug = $this->uri ?? $handle;
            $this->step("index page /{$slug}", $this->createIndexPage($handle, $slug, $section->name));

            // 6. Pickable in Related.
            $related = $fields->getFieldByHandle('relatedEntries');
            if ($related instanceof Entries && is_array($related->sources) && !in_array('section:' . $section->uid, $related->sources, true)) {
                $related->sources = [...$related->sources, 'section:' . $section->uid];
                $fields->saveField($related);
                $this->step('Related field source', 'added');
            } else {
                $this->step('Related field source', null);
            }
        } catch (RuntimeException $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\nBy hand (PHP config isn't rewritten by code):\n", Console::FG_YELLOW);
        $this->stdout("  config/stables/related.php: add '{$handle}' to 'sections' and 'fillSections'.\n");

        if ($spec['schemaType']) {
            $this->stdout("  config/stables/seo.php sectionDefaults: '{$handle}' => ['schemaType' => '{$spec['schemaType']}'],\n");
        }

        $this->stdout("  Review and enable the index page, then commit config/project.\n");

        return ExitCode::OK;
    }

    /**
     * Usage: php craft stablestwigextensions/content/attach-topics <event|job|…>
     *
     * Adds the topics field to the end of the entry type's Settings tab, in place.
     */
    public function actionAttachTopics(string $entryTypeHandle): int
    {
        $entryType = Craft::$app->getEntries()->getEntryTypeByHandle($entryTypeHandle);
        $topics = Craft::$app->getFields()->getFieldByHandle('topics');

        if ($topics === null) {
            $this->stderr("No topics field on this site. Run php craft up first.\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        if ($entryType === null || !$this->hasUrls($entryType)) {
            $this->stderr("\"{$entryTypeHandle}\" isn't an entry type in a section with URLs.\n", Console::FG_RED);

            return ExitCode::USAGE;
        }

        try {
            $this->step("topics on {$entryTypeHandle}", (new ContentModel())->addField($entryTypeHandle, $topics, 'Settings') ? 'added' : null);
        } catch (RuntimeException $e) {
            $this->stderr($e->getMessage() . "\n", Console::FG_RED);

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("Commit config/project.\n");

        return ExitCode::OK;
    }

    private function rename(\craft\models\Section $section, string $typeHandle): void
    {
        $entries = Craft::$app->getEntries();

        if ($this->name === null && $this->uri === null) {
            $this->step('name and URI', null, 'kept');

            return;
        }

        $changed = false;

        if ($this->name !== null && $section->name !== $this->name) {
            $section->name = $this->name;
            $changed = true;
        }

        if ($this->uri !== null) {
            foreach ($section->getSiteSettings() as $settings) {
                $format = $this->uri . '/{slug}';

                if ($settings->uriFormat !== $format) {
                    $settings->uriFormat = $format;
                    $changed = true;
                }
            }
        }

        if ($changed && !$entries->saveSection($section)) {
            throw new RuntimeException("Couldn't rename the section: " . implode(', ', $section->getErrorSummary(true)));
        }

        $this->step('section name and URI', $changed ? 'updated' : null);

        $typeName = $this->typeName ?? $this->name;
        $entryType = $entries->getEntryTypeByHandle($typeHandle);

        if ($typeName !== null && $entryType !== null && $entryType->name !== $typeName) {
            $entryType->name = $typeName;

            if (!$entries->saveEntryType($entryType)) {
                throw new RuntimeException("Couldn't rename the entry type: " . implode(', ', $entryType->getErrorSummary(true)));
            }

            $this->step('entry type name', 'updated');
        }
    }

    private function addPostTypeOption(string $handle, string $label): bool
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle('postType');

        if (!$field instanceof \craft\fields\Dropdown) {
            throw new RuntimeException('No postType dropdown on this site.');
        }

        foreach ($field->options as $option) {
            if (($option['value'] ?? null) === $handle) {
                return false;
            }
        }

        $field->options = [...$field->options, ['label' => $label, 'value' => $handle, 'default' => '']];

        if (!$fields->saveField($field)) {
            throw new RuntimeException("Couldn't add the postType option: " . implode(', ', $field->getErrorSummary(true)));
        }

        return true;
    }

    private function createIndexPage(string $handle, string $slug, string $title): ?string
    {
        $entries = Craft::$app->getEntries();
        $pages = $entries->getSectionByHandle('pages');
        $pageType = $entries->getEntryTypeByHandle('page');

        if ($pages === null || $pageType === null) {
            return 'no pages section; skipped';
        }

        if (Entry::find()->section('pages')->slug($slug)->status(null)->exists()) {
            return null;
        }

        $page = new Entry([
            'sectionId' => $pages->id,
            'typeId' => $pageType->id,
            'title' => $title,
            'slug' => $slug,
            'enabled' => false,
        ]);
        $page->setFieldValue('pageBuilder', [
            'new1' => ['type' => 'posts', 'enabled' => true, 'fields' => ['postType' => $handle]],
        ]);

        if (!Craft::$app->getElements()->saveElement($page)) {
            throw new RuntimeException("Couldn't create the index page: " . implode(', ', $page->getErrorSummary(true)));
        }

        return 'created, disabled';
    }

    private function hasUrls(\craft\models\EntryType $entryType): bool
    {
        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            foreach ($section->getEntryTypes() as $type) {
                if ($type->id === $entryType->id) {
                    return (bool)array_filter($section->getSiteSettings(), static fn($s) => $s->hasUrls);
                }
            }
        }

        return false;
    }

    private function step(string $what, string|bool|null $did, ?string $note = null): void
    {
        if ($did === false) {
            $this->stdout(sprintf("  %-32s %s\n", $what, $note ?? 'skipped'), Console::FG_YELLOW);
        } elseif ($did === null) {
            $this->stdout(sprintf("  %-32s %s\n", $what, $note ?? 'already done'));
        } else {
            $this->stdout(sprintf("  %-32s %s\n", $what, $did), Console::FG_GREEN);
        }
    }
}
