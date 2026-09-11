<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * No-op. This used to seed Starter Header nodes into justinholtweb/craft-
 * free-nav's 'header' menu (worked around a bug in that plugin where
 * "Entry"-type nodes couldn't actually be created from the CP). FreeNav was
 * removed entirely in 7ef3c65 ("Wire the front-end nav templates onto the
 * new nav module, remove FreeNav") in favor of craft-modules' own nav
 * module (see m260718_220000_addNavTables onward), which leaves
 * FreeNav::getInstance() undefined — this migration is permanently
 * unrunnable as written.
 *
 * There's no equivalent seeding to reinstate: the new nav module's tables
 * (m260718_220000_addNavTables) create an empty nav_settings row only, and
 * neither that migration nor any later nav migration nor
 * craft-modules/modules/nav itself seeds a 'header' nav or its nodes — a
 * site's header nav is created by hand in the CP after install, same as any
 * other nav. So this migration's original job has nothing to be superseded
 * by; it's simply retired.
 */
class m260717_053522_addStarterHeaderNavNodes extends Migration
{
    public function safeUp(): bool
    {
        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}
