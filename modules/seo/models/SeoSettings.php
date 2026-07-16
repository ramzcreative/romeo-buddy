<?php

namespace modules\seo\models;

use Craft;
use craft\base\Model;
use craft\elements\Asset;

/**
 * Sitewide SEO defaults, persisted via project config (see
 * modules/seo/services/SeoSettingsService.php) rather than a Global Set.
 *
 * Asset relations are stored by UID (logoUid/defaultOgImageUid), not ID —
 * project config is synced across environments/databases via YAML, and
 * element IDs aren't portable across databases the way UIDs are.
 */
class SeoSettings extends Model
{
    public ?string $orgName = null;
    public ?string $logoUid = null;
    public ?string $defaultOgImageUid = null;
    public ?string $twitterHandle = null;
    public ?string $twitterUrl = null;
    public ?string $facebookUrl = null;
    public ?string $instagramUrl = null;
    public ?string $linkedinUrl = null;
    public ?string $youtubeUrl = null;
    public ?string $tiktokUrl = null;
    public ?string $pinterestUrl = null;
    public ?string $threadsUrl = null;
    public ?string $titleSeparator = null;
    public ?string $defaultTitleTemplate = null;
    public ?string $defaultDescription = null;

    // Search engine ownership-verification tokens. Not secrets — these are
    // meant to be published in public HTML (that's how verification works),
    // so project-config-backed CP settings are the right fit here, same as
    // everything else in this model — no .env entry needed.
    public ?string $googleSiteVerification = null;
    public ?string $bingSiteVerification = null;

    public function getLogo(): ?Asset
    {
        return $this->logoUid
            ? Craft::$app->getElements()->getElementByUid($this->logoUid, Asset::class)
            : null;
    }

    public function getDefaultOgImage(): ?Asset
    {
        return $this->defaultOgImageUid
            ? Craft::$app->getElements()->getElementByUid($this->defaultOgImageUid, Asset::class)
            : null;
    }
}
