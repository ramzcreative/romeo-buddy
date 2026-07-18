<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Asset;
use craft\helpers\Json;

/**
 * Moves modules\seo\services\SeoSettingsService and
 * modules\themepicker\services\ThemeRegistry off project config onto
 * dedicated {{%seo_settings}}/{{%theme_settings}} tables (see those
 * services' own doc comments for why: project config edits made through the
 * CP under allowAdminChanges=false only ever update project config's
 * internal store, never the tracked YAML, so a later deploy that syncs
 * project config for any unrelated reason could silently revert a client's
 * live-edited SEO settings or active theme back to whatever's committed).
 *
 * Backfills both new tables from whatever's currently in project config so
 * nothing already configured is lost, then removes those project config
 * paths. Safe to run more than once — upsert() targets id=1 either way.
 */
class m260718_175602_addSeoAndThemeSettingsTables extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%seo_settings}}', [
            'id' => $this->primaryKey(),
            'orgName' => $this->string(),
            'logoId' => $this->integer(),
            'defaultOgImageId' => $this->integer(),
            'founder' => $this->text(),
            'foundingDate' => $this->string(),
            'awards' => $this->text(),
            'knowsAbout' => $this->text(),
            'phone' => $this->string(),
            'email' => $this->string(),
            'addressLine1' => $this->text(),
            'addressLine2' => $this->text(),
            'locality' => $this->string(),
            'administrativeArea' => $this->string(),
            'postalCode' => $this->string(),
            'countryCode' => $this->string(),
            'latitude' => $this->string(),
            'longitude' => $this->string(),
            'openingHours' => $this->json(),
            'priceCurrency' => $this->string(),
            'priceRange' => $this->string(),
            'twitterHandle' => $this->string(),
            'twitterUrl' => $this->string(),
            'facebookUrl' => $this->string(),
            'instagramUrl' => $this->string(),
            'linkedinUrl' => $this->string(),
            'youtubeUrl' => $this->string(),
            'tiktokUrl' => $this->string(),
            'pinterestUrl' => $this->string(),
            'threadsUrl' => $this->string(),
            'titleSeparator' => $this->string(),
            'defaultTitleTemplate' => $this->text(),
            'defaultDescription' => $this->text(),
            'googleSiteVerification' => $this->string(),
            'bingSiteVerification' => $this->string(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
        $this->addForeignKey(null, '{{%seo_settings}}', ['logoId'], '{{%assets}}', ['id'], 'SET NULL', null);
        $this->addForeignKey(null, '{{%seo_settings}}', ['defaultOgImageId'], '{{%assets}}', ['id'], 'SET NULL', null);

        $this->createTable('{{%theme_settings}}', [
            'id' => $this->primaryKey(),
            'activeTheme' => $this->string()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $projectConfig = Craft::$app->getProjectConfig();

        $seoConfig = $projectConfig->get('seo.settings') ?? [];
        $logoId = !empty($seoConfig['logoUid'])
            ? Craft::$app->getElements()->getElementByUid($seoConfig['logoUid'], Asset::class)?->id
            : null;
        $ogImageId = !empty($seoConfig['defaultOgImageUid'])
            ? Craft::$app->getElements()->getElementByUid($seoConfig['defaultOgImageUid'], Asset::class)?->id
            : null;
        unset($seoConfig['logoUid'], $seoConfig['defaultOgImageUid']);
        $seoConfig['openingHours'] = Json::encode($seoConfig['openingHours'] ?? []);
        $seoConfig['logoId'] = $logoId;
        $seoConfig['defaultOgImageId'] = $ogImageId;

        $this->db->createCommand()
            ->upsert('{{%seo_settings}}', array_merge(['id' => 1], $seoConfig))
            ->execute();

        $activeTheme = $projectConfig->get('themePicker.activeTheme') ?? 'default';
        $this->db->createCommand()
            ->upsert('{{%theme_settings}}', ['id' => 1, 'activeTheme' => $activeTheme])
            ->execute();

        // ProjectConfig::$readOnly is derived purely from allowAdminChanges
        // (see craft\helpers\App::projectConfigConfig()) with no exemption
        // for console/migration context — confirmed the hard way, this
        // migration's remove() calls threw the same NotSupportedException
        // the services used to. A one-time, version-controlled migration is
        // exactly the kind of trusted, intentional operation the readOnly
        // bypass technique (see SeoSettingsService/ThemeRegistry's old v1.1.1
        // code, now removed) is for — arguably more so than a live CP save.
        $readOnly = $projectConfig->readOnly;
        $projectConfig->readOnly = false;
        try {
            $projectConfig->remove('seo.settings');
            $projectConfig->remove('themePicker.activeTheme');
        } finally {
            $projectConfig->readOnly = $readOnly;
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%seo_settings}}');
        $this->dropTableIfExists('{{%theme_settings}}');

        return true;
    }
}
