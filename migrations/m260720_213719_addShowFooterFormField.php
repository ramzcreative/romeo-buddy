<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Lightswitch;

/**
 * Adds a 'Show Footer Form' lightswitch field (default on) to the 'page'
 * and 'landingPage' entry types' existing Settings tab, so an editor can
 * hide the sitewide footer form (_partials/footer.twig's footerForm
 * global) on a per-page basis. Off by exception, not by default — most
 * pages want the footer form, so the field defaults to true and only
 * needs to be touched on the pages that shouldn't show it.
 *
 * Ported from stables — same field UID reused, keeping the two sibling
 * sites' project configs aligned the way their shared entry type/field
 * UIDs already are. Note romeo-buddy's 'landingPage' entry type has its
 * own distinct UID (diverged from stables), but this migration resolves
 * it by handle, so that doesn't matter here.
 */
class m260720_213719_addShowFooterFormField extends Migration
{
    private const FIELD_HANDLE = 'showFooterForm';
    private const FIELD_UID = '525bfa27-55fb-4141-9482-18c8cbf8581e';
    private const ENTRY_TYPE_HANDLES = ['page', 'landingPage'];

    public function safeUp(): bool
    {
        $fieldsService = Craft::$app->getFields();
        $entriesService = Craft::$app->getEntries();

        $field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE);

        if (!$field) {
            $field = new Lightswitch([
                'uid' => self::FIELD_UID,
                'name' => 'Show Footer Form',
                'handle' => self::FIELD_HANDLE,
                'instructions' => 'Show the sitewide footer form (if one is set in Settings > Footer) at the bottom of this page.',
                'default' => true,
            ]);

            if (!$fieldsService->saveField($field)) {
                throw new \Exception("Couldn't save the 'showFooterForm' field: " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        foreach (self::ENTRY_TYPE_HANDLES as $handle) {
            $entryType = $entriesService->getEntryTypeByHandle($handle);

            if (!$entryType) {
                throw new \Exception("Couldn't find the '{$handle}' entry type — expected it to already exist.");
            }

            $fieldLayout = $entryType->getFieldLayout();
            $tabs = $fieldLayout->getTabs();
            $settingsTab = null;

            foreach ($tabs as $tab) {
                if ($tab->name === 'Settings') {
                    $settingsTab = $tab;
                    break;
                }
            }

            if (!$settingsTab) {
                throw new \Exception("The '{$handle}' entry type has no 'Settings' tab.");
            }

            $existingHandles = array_map(
                fn($element) => $element instanceof CustomField ? $element->getField()?->handle : null,
                $settingsTab->getElements(),
            );

            if (!in_array(self::FIELD_HANDLE, $existingHandles, true)) {
                $settingsTab->setElements([
                    ...$settingsTab->getElements(),
                    new CustomField($field),
                ]);
                $fieldLayout->setTabs($tabs);
                $entryType->setFieldLayout($fieldLayout);

                if (!$entriesService->saveEntryType($entryType)) {
                    throw new \Exception("Couldn't save the '{$handle}' entry type: " . implode(', ', $entryType->getErrorSummary(true)));
                }
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fieldsService = Craft::$app->getFields();

        if ($field = $fieldsService->getFieldByHandle(self::FIELD_HANDLE)) {
            $fieldsService->deleteField($field);
        }

        return true;
    }
}
