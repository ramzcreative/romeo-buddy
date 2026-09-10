<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use modules\themepicker\fields\ColorChip;

/**
 * Points the `backgroundRole` field at its renamed class.
 *
 * `BackgroundRole` became `ColorChip` once it gained palettes: block
 * backgrounds are one palette, and a site can define others (icon
 * backgrounds, accents) with each field choosing one — so the type is
 * named for the mechanism rather than for its first use.
 *
 * The field HANDLE deliberately does not change. `backgroundRole` still
 * describes what this particular field is for, eleven templates read
 * `entry.backgroundRole`, and renaming it would edit every one of them for
 * no gain. A site may well end up with several ColorChip fields —
 * `backgroundRole`, `iconBackground` — which is the point.
 *
 * Goes through project config rather than updating the `fields` table
 * directly: project config is the source of truth, Craft's own field
 * service applies the change to the database from it, and a direct DB
 * write would be silently reverted the next time config is applied.
 */
class m260910_170000_renameBackgroundRoleFieldType extends Migration
{
    private const HANDLE = 'backgroundRole';
    private const OLD_TYPE = 'modules\\themepicker\\fields\\BackgroundRole';

    public function safeUp(): bool
    {
        $field = Craft::$app->getFields()->getFieldByHandle(self::HANDLE);

        if ($field === null) {
            echo "    > no `backgroundRole` field; nothing to repoint\n";

            return true;
        }

        if ($field instanceof ColorChip) {
            echo "    > `backgroundRole` is already a ColorChip\n";

            return true;
        }

        Craft::$app->getProjectConfig()->set(
            "fields.{$field->uid}.type",
            ColorChip::class,
            'Rename BackgroundRole to ColorChip'
        );

        echo "    > repointed `backgroundRole` to " . ColorChip::class . "\n";

        return true;
    }

    public function safeDown(): bool
    {
        $field = Craft::$app->getFields()->getFieldByHandle(self::HANDLE);

        if ($field !== null) {
            Craft::$app->getProjectConfig()->set("fields.{$field->uid}.type", self::OLD_TYPE);
        }

        return true;
    }
}
