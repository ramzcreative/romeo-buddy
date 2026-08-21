<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Renames the redirect tables now that redirects are their own module.
 *
 * They landed as {{%seo_redirects}} / {{%seo_404s}} in m260820_170000, when
 * redirects lived inside modules/seo. They don't any more — on-page SEO and
 * redirect management are different jobs with different audiences, and
 * separating them is what lets the two be granted to different people
 * (SEOmatic and Retour are split by the same team for the same reason).
 *
 * A `seo_` prefix on tables the seo module never touches is a lie that gets
 * more expensive the longer it stands, and renaming is free right now: these
 * tables exist only in local development and hold nothing. After a client
 * site has real redirects in them it stops being free.
 *
 * Guarded both ways so it's a no-op on a site that never ran the original.
 */
class m260820_190000_renameRedirectTablesForOwnModule extends Migration
{
    public function safeUp(): bool
    {
        foreach ([
            '{{%seo_redirects}}' => '{{%redirects_rules}}',
            '{{%seo_404s}}' => '{{%redirects_404s}}',
        ] as $from => $to) {
            if ($this->db->tableExists($from) && !$this->db->tableExists($to)) {
                $this->renameTable($from, $to);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        foreach ([
            '{{%redirects_rules}}' => '{{%seo_redirects}}',
            '{{%redirects_404s}}' => '{{%seo_404s}}',
        ] as $from => $to) {
            if ($this->db->tableExists($from) && !$this->db->tableExists($to)) {
                $this->renameTable($from, $to);
            }
        }

        return true;
    }
}
