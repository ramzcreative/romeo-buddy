<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * Adds {{%seo_settings}}.articleSections — the CP-editable half of "which
 * sections hold articles, and what schema.org type are they".
 *
 * The seo module used to answer that with a hardcoded `blog` handle in four
 * separate places (buildArticle(), the og:type tag, the RSS feed, and the
 * breadcrumb index lookup). A site whose blog is called `insights` got no
 * Article markup, og:type=website on every post, and no feed — silently.
 *
 * Stored as a list of {section, schemaType} rows rather than a flat list of
 * handles, so a site that separates blog from news can say BlogPosting for
 * one and NewsArticle for the other. A plain "these are articles" checkbox
 * list couldn't express that.
 *
 * json(), not text: read back through SeoSettingsService::decodeJsonColumn()
 * like openingHours/areaServed/credentials, and written as a raw PHP array —
 * see saveSettings() for why pre-encoding breaks it.
 *
 * Has to land BEFORE the module version bump that reads it:
 * SeoSettingsService::saveSettings() upserts getAttributes() wholesale, so a
 * model property with no matching column errors on every save.
 */
class m260820_120000_addSeoArticleSections extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->columnExists('{{%seo_settings}}', 'articleSections')) {
            $this->addColumn('{{%seo_settings}}', 'articleSections', $this->json()->after('defaultDescription'));
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->columnExists('{{%seo_settings}}', 'articleSections')) {
            $this->dropColumn('{{%seo_settings}}', 'articleSections');
        }

        return true;
    }
}
