<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use modules\seo\fields\Seo;

/**
 * Folds the seo field's showServiceType + showCourse settings into one
 * showSchema switch (craft-modules v1.31.0).
 *
 * Those two asked "is this a service page?" / "is this a course page?", which
 * a field setting cannot answer — there is one `seo` field used everywhere,
 * so the switch was sitewide while the question was per-page. Neither ever
 * gated any markup: buildService() needs a non-empty serviceType and
 * buildCourse() a non-empty courseSubject either way. They only decided
 * whether an editor saw an input, and being field settings meant turning one
 * on for a single page was a deploy.
 *
 * showSchema now reveals both, inside a collapsed "Schema details"
 * disclosure, and defaults to ON — the disclosure stays shut until an entry
 * has values and a blank input publishes nothing, so showing it costs a
 * closed row rather than a deploy to enable it.
 *
 * This migration only overrides that default where the site expressed an
 * opinion: on where either old switch was on, off where one was explicitly
 * off, and left at the default for a site carrying neither key (it predates
 * them, so its silence isn't a decision). Nothing an editor could previously
 * see disappears, and nothing they deliberately hid comes back.
 *
 * The old values are read from project config rather than the field model,
 * because the model no longer declares those properties — Yii's
 * setAttributes() silently skips config keys with no matching attribute, so
 * the field loads fine and the stale keys simply sit in the YAML until this
 * runs. Saving through the fields service is what rewrites them out; see
 * this repo's CLAUDE.md on never hand-editing project.yaml.
 */
class m260820_140000_consolidateSeoSchemaFieldSettings extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $field = $fields->getFieldByHandle('seo');

        if (!$field instanceof Seo) {
            echo "    > no `seo` field on this site; nothing to do\n";
            return true;
        }

        $stored = Craft::$app->getProjectConfig()->get('fields.' . $field->uid . '.settings') ?? [];

        // Only override the new default where the site actually expressed an
        // opinion. A site that carries neither old key never had these inputs
        // to turn off — it predates them — so forcing it off would be reading
        // a decision into silence. Those inherit showSchema's own default
        // (on), which is what a newly created field gets too.
        //
        // A site that explicitly set either switch keeps that answer, so
        // nothing an editor could previously see disappears, and nothing they
        // deliberately hid comes back.
        $expressedAnOpinion = array_key_exists('showServiceType', $stored)
            || array_key_exists('showCourse', $stored);

        if ($expressedAnOpinion) {
            $field->showSchema = !empty($stored['showServiceType']) || !empty($stored['showCourse']);
        }

        if (!$fields->saveField($field)) {
            throw new \Exception('Could not save the seo field: ' . implode(', ', $field->getFirstErrors()));
        }

        echo '    > showSchema set to ' . ($field->showSchema ? 'true' : 'false') . ", old switches cleared\n";

        return true;
    }

    public function safeDown(): bool
    {
        return false;
    }
}
