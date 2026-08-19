<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Adds {{%seo_settings}}.founderType — 'person' or 'organization'.
 *
 * schema.org's `founder` accepts either ("a person or organization who
 * founded this organization"), but craft-modules v1.23.0-v1.27.0 hardcoded
 * Person, so a site whose founder is a parent company or imprint published a
 * business as a human being.
 *
 * Null is treated as 'person' by the resolver, which is what those versions
 * already emitted — so existing sites keep their current output until an
 * editor says otherwise.
 */
class m260818_220000_addSeoFounderType extends Migration
{
    private const TABLE = '{{%seo_settings}}';
    private const COLUMN = 'founderType';

    public function safeUp(): bool
    {
        if ($this->db->getTableSchema(self::TABLE, true)->getColumn(self::COLUMN) === null) {
            $this->addColumn(self::TABLE, self::COLUMN, $this->string());
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->getTableSchema(self::TABLE, true)->getColumn(self::COLUMN) !== null) {
            $this->dropColumn(self::TABLE, self::COLUMN);
        }

        return true;
    }
}
