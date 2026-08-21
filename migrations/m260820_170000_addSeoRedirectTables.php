<?php

namespace craft\contentmigrations;

use craft\db\Migration;

/**
 * Tables for CP-managed redirects and the 404 log they're built from.
 *
 * Craft 5 already redirects on 404 from a `config/redirects.php` file, and
 * this deliberately does NOT replace that matching — see RedirectService,
 * which hands every rule to Craft's own craft\web\RedirectRule so the token
 * syntax (`<slug:[\w-]+>`) is identical whether a rule came from the file or
 * this table. What the file can't do is be edited by the person launching the
 * site, which is the whole point: a rebuild changes URLs, and whoever notices
 * the broken link is rarely the person who can deploy a config change.
 *
 * Per-site from the start. Retrofitting siteId onto a redirects table after a
 * multi-site client exists means backfilling rows whose intended scope nobody
 * remembers; a null siteId means "every site", which is the common case.
 */
class m260820_170000_addSeoRedirectTables extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%seo_redirects}}')) {
            $this->createTable('{{%seo_redirects}}', [
                'id' => $this->primaryKey(),
                // Null = applies to every site.
                'siteId' => $this->integer(),
                // Matched against the request's full path. Craft's token
                // syntax works here, e.g. 'blog/<year:\d{4}>/<slug:[\w-]+>'.
                'source' => $this->string(255)->notNull(),
                'destination' => $this->string(255)->notNull(),
                'statusCode' => $this->integer()->notNull()->defaultValue(301),
                'caseSensitive' => $this->boolean()->notNull()->defaultValue(false),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                // Evaluation order. First match wins, so a broad pattern
                // placed above a specific one would shadow it.
                'sortOrder' => $this->integer()->notNull()->defaultValue(0),
                // Evidence the rule is doing something. A rule with no hits
                // months after a launch is usually a guess that never landed.
                'hitCount' => $this->integer()->notNull()->defaultValue(0),
                'lastHit' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%seo_redirects}}', ['siteId', 'enabled', 'sortOrder'], false);
        }

        if (!$this->db->tableExists('{{%seo_404s}}')) {
            $this->createTable('{{%seo_404s}}', [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer(),
                'uri' => $this->string(255)->notNull(),
                'referrer' => $this->string(255),
                'hitCount' => $this->integer()->notNull()->defaultValue(1),
                'lastHit' => $this->dateTime()->notNull(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            // One row per URI per site, incremented on repeat — a log that
            // appended would be unreadable within a week of a bad launch and
            // would grow without bound.
            $this->createIndex(null, '{{%seo_404s}}', ['siteId', 'uri'], true);
            $this->createIndex(null, '{{%seo_404s}}', ['lastHit'], false);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%seo_404s}}');
        $this->dropTableIfExists('{{%seo_redirects}}');

        return true;
    }
}
