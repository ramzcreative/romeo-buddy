<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\enums\PropagationMethod;
use craft\models\Section;
use craft\models\Section_SiteSettings;

/**
 * A "Page Templates" section: predefined page-builder entries an editor can
 * start a new page from, instead of a blank one. The picker and the copy
 * mechanism live in craft-modules' `page-templates` module, which is dormant
 * on any site where this section doesn't exist.
 *
 * Templates reuse the site's own page entry types (Craft 5 entry types are
 * shared across sections), so a template carries exactly the fields of the
 * page it seeds and there is nothing extra to keep in sync. The section has
 * no URLs, which keeps templates out of routing, the sitemap and nav.
 *
 * A structure so the order editors see in the picker is drag-set here.
 * Starter templates are authored in the CP, deliberately not seeded.
 */
class m260908_200000_addPageTemplatesSection extends Migration
{
    private const SECTION_HANDLE = 'pageTemplates';
    private const ENTRY_TYPE_HANDLES = ['page', 'landingPage'];

    public function safeUp(): bool
    {
        $entries = Craft::$app->getEntries();

        // Adopt-if-present: `php craft up` applies pending project config
        // before content migrations, so this may already exist from a
        // committed sections/*.yaml.
        $section = $entries->getSectionByHandle(self::SECTION_HANDLE);

        if (!$section) {
            $section = new Section([
                'name' => 'Page Templates',
                'handle' => self::SECTION_HANDLE,
                'type' => Section::TYPE_STRUCTURE,
                'maxLevels' => 1,
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
        }

        $entryTypes = [];
        foreach (self::ENTRY_TYPE_HANDLES as $handle) {
            if ($entryType = $entries->getEntryTypeByHandle($handle)) {
                $entryTypes[] = $entryType;
            }
        }

        if (!$entryTypes) {
            throw new \Exception("Couldn't find any of the page entry types (" . implode(', ', self::ENTRY_TYPE_HANDLES) . ") — expected at least one to exist.");
        }

        $section->setEntryTypes($entryTypes);

        if (!$entries->saveSection($section)) {
            throw new \Exception("Couldn't save the 'Page Templates' section: " . implode(', ', $section->getErrorSummary(true)));
        }

        return true;
    }

    public function safeDown(): bool
    {
        $entries = Craft::$app->getEntries();

        if ($section = $entries->getSectionByHandle(self::SECTION_HANDLE)) {
            $entries->deleteSection($section);
        }

        return true;
    }
}
