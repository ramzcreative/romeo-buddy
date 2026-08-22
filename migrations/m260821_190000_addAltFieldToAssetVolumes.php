<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\assets\AltField;

/**
 * Adds Craft's native Alt Text field to the asset volumes' field layouts.
 *
 * WHY THIS IS NEEDED AT ALL
 * Craft supports alt text on every asset, but only surfaces the input if
 * `AltField` is present in the volume's field layout — it is offered in the
 * layout designer rather than added automatically. None of this site's volumes
 * had it, so `craft_assets.alt` was NULL on every row and an editor had
 * nowhere in the CP to type alt text even if they wanted to.
 *
 * That is the whole reason `_utilities/transforms.twig` falls back to
 * `imageAsset.title`, and since Craft derives an asset's title from its
 * filename, every non-empty alt on the front end was a filename. Removing that
 * fallback without this migration would just replace filenames with empty
 * strings; this has to land first.
 *
 * DELIBERATELY NOT REQUIRED
 * `requirable` is left off. Making alt mandatory would block saving any asset
 * without it, including the decorative ones where empty alt is the CORRECT
 * answer, and would break every existing upload the moment someone re-saves it.
 * Whether to require it is an editorial decision, not a migration's.
 *
 * SAFETY
 * Purely additive. It appends one element to an existing tab and never removes,
 * replaces or reorders anything — a volume that somehow already has the field
 * is skipped. Nothing here touches content rows.
 *
 * Note the setTabs()/setElements() ordering below: setElements() on a tab that
 * is not yet attached to its layout throws "Field layout tab is missing its
 * field layout". Build the tabs, call setTabs(), THEN setElements().
 */
class m260821_190000_addAltFieldToAssetVolumes extends Migration
{
    public function safeUp(): bool
    {
        $volumesService = Craft::$app->getVolumes();

        foreach ($volumesService->getAllVolumes() as $volume) {
            $layout = $volume->getFieldLayout();
            $tabs = $layout->getTabs();

            if (!$tabs) {
                Craft::warning("Volume '{$volume->handle}' has no field layout tabs — skipped.", __METHOD__);
                continue;
            }

            // Already present? Leave it exactly as the CP configured it —
            // re-adding would give the editor two Alt Text boxes.
            $alreadyHas = false;

            foreach ($tabs as $tab) {
                foreach ($tab->getElements() as $element) {
                    if ($element instanceof AltField) {
                        $alreadyHas = true;
                        break 2;
                    }
                }
            }

            if ($alreadyHas) {
                echo "    > {$volume->handle}: already has Alt Text, skipped\n";
                continue;
            }

            $tab = $tabs[0];

            $layout->setTabs($tabs);
            $tab->setElements(array_merge($tab->getElements(), [new AltField()]));
            $volume->setFieldLayout($layout);

            if (!$volumesService->saveVolume($volume)) {
                throw new \Exception(
                    "Couldn't add Alt Text to the '{$volume->handle}' volume: "
                    . implode(', ', $volume->getErrorSummary(true))
                );
            }

            echo "    > {$volume->handle}: Alt Text field added\n";
        }

        return true;
    }

    public function safeDown(): bool
    {
        // Reversible, and worth reversing cleanly: leaving the field behind
        // after a rollback would leave editors typing alt text that the
        // reverted templates no longer read.
        $volumesService = Craft::$app->getVolumes();

        foreach ($volumesService->getAllVolumes() as $volume) {
            $layout = $volume->getFieldLayout();
            $tabs = $layout->getTabs();

            if (!$tabs) {
                continue;
            }

            $changed = false;

            foreach ($tabs as $tab) {
                $kept = array_values(array_filter(
                    $tab->getElements(),
                    fn($element) => !$element instanceof AltField,
                ));

                if (count($kept) !== count($tab->getElements())) {
                    $changed = true;
                    $layout->setTabs($tabs);
                    $tab->setElements($kept);
                }
            }

            if ($changed) {
                $volume->setFieldLayout($layout);
                $volumesService->saveVolume($volume);
            }
        }

        return true;
    }
}
