<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Columns behind the SEO settings' expanded Founder fields and the new
 * Credentials table — see modules\seo\services\StructuredDataBuilder's
 * buildPerson()/buildCredentials() in craft-modules v1.23.0.
 *
 * Must land before that module version is pulled:
 * SeoSettingsService::saveSettings() upserts $settings->getAttributes()
 * wholesale, so a model property with no matching column breaks every SEO
 * settings save.
 *
 * All nullable. Nothing changes in what a site publishes until an editor
 * fills something in — the Person node is gated on the existing Founder
 * name, which no site has set.
 */
class m260818_230000_addSeoPersonAndCredentials extends Migration
{
    private const TABLE = '{{%seo_settings}}';

    private const COLUMNS = [
        'founderJobTitle' => 'string',
        'founderImageId' => 'integer',
        'founderDescription' => 'text',
        // Comma-separated, same convention as awards/knowsAbout.
        'founderSameAs' => 'text',
        // Rows of {name, credentialCategory, issuedBy, url} — json(), same as
        // openingHours and areaServed.
        'credentials' => 'json',
    ];

    public function safeUp(): bool
    {
        $schema = $this->db->getTableSchema(self::TABLE, true);
        $added = [];

        foreach (self::COLUMNS as $column => $type) {
            if ($schema->getColumn($column) !== null) {
                continue;
            }

            $definition = match ($type) {
                'json' => $this->json(),
                'text' => $this->text(),
                'integer' => $this->integer(),
                default => $this->string(),
            };

            $this->addColumn(self::TABLE, $column, $definition);
            $added[] = $column;
        }

        // Only when this run is what created the column — $schema above is a
        // snapshot from before the loop, so re-reading it here would say the
        // column is absent and add a duplicate key on a second run. Matches
        // how logoId/defaultOgImageId are wired: the setting empties itself
        // if the asset is deleted, rather than dangling.
        if (in_array('founderImageId', $added, true)) {
            $this->addForeignKey(null, self::TABLE, ['founderImageId'], '{{%assets}}', ['id'], 'SET NULL', null);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $schema = $this->db->getTableSchema(self::TABLE, true);

        // The foreign key has to go first — MySQL refuses to drop a column an
        // FK still references, and a down that fails halfway leaves the table
        // in a state neither safeUp() nor safeDown() describes.
        foreach ($schema->foreignKeys as $name => $definition) {
            if (array_key_exists('founderImageId', $definition)) {
                $this->dropForeignKey($name, self::TABLE);
            }
        }

        foreach (array_keys(self::COLUMNS) as $column) {
            if ($schema->getColumn($column) !== null) {
                $this->dropColumn(self::TABLE, $column);
            }
        }

        return true;
    }
}
