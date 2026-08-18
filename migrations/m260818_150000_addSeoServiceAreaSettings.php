<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Service-area business support for modules\seo — adds the columns behind
 * the SEO settings' new "Service Area" section (see
 * modules\seo\models\SeoSettings and StructuredDataBuilder::buildLocalBusiness()).
 *
 * This has to land before the ramzcreative/craft-modules bump that ships
 * those settings: SeoSettingsService::saveSettings() upserts
 * $settings->getAttributes() wholesale, so a model property with no matching
 * column makes every SEO settings save fail on an unknown column.
 *
 * Every column is nullable or defaults to false. Nothing here changes what an
 * existing site publishes until an editor fills it in — an empty businessType
 * still resolves to the generic 'LocalBusiness' these sites already emitted.
 */
class m260818_150000_addSeoServiceAreaSettings extends Migration
{
    private const COLUMNS = [
        // schema.org LocalBusiness subtype; empty = generic LocalBusiness.
        'businessType' => 'string',
        // Wikidata (or similar) URI for businesses schema.org has no type for.
        'additionalType' => 'text',
        // Service-area business: suppress streetAddress from the JSON-LD.
        'hideStreetAddress' => 'boolean',
        // Rows of {name, type} — json(), same as openingHours.
        'areaServed' => 'json',
        // Radius in km; only meaningful alongside latitude/longitude.
        'serviceRadius' => 'string',
        'googleBusinessProfileUrl' => 'text',
        // Opt-in: self-serving aggregate review ratings are a Google policy
        // risk on LocalBusiness. Default off, including for existing rows.
        'enableAggregateRating' => 'boolean',
    ];

    public function safeUp(): bool
    {
        $table = '{{%seo_settings}}';
        // Read once with refresh, so a partially-applied earlier run is seen
        // accurately rather than through a stale cached schema.
        $schema = $this->db->getTableSchema($table, true);

        foreach (self::COLUMNS as $column => $type) {
            // Idempotent — this migration has a sibling in every site that
            // branched from stables, and re-running it shouldn't explode.
            if ($schema->getColumn($column) !== null) {
                continue;
            }

            $definition = match ($type) {
                'boolean' => $this->boolean()->notNull()->defaultValue(false),
                'json' => $this->json(),
                'text' => $this->text(),
                default => $this->string(),
            };

            $this->addColumn($table, $column, $definition);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $table = '{{%seo_settings}}';
        $schema = $this->db->getTableSchema($table, true);

        foreach (array_keys(self::COLUMNS) as $column) {
            if ($schema->getColumn($column) !== null) {
                $this->dropColumn($table, $column);
            }
        }

        return true;
    }
}
