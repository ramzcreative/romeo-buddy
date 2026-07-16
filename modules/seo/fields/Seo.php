<?php

namespace modules\seo\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Cp;
use modules\seo\models\SeoData;
use modules\seo\services\SeoResolver;
use modules\seo\web\assets\SeoAsset;
use yii\db\Schema;

/**
 * Per-entry SEO override field — title, description, keywords, image,
 * robots. Backed by a single Schema::TYPE_JSON column, same storage
 * strategy as Craft's own native `craft\fields\Json` field: normalizeValue()
 * only needs to wrap an already-decoded array (or null) into a model: the
 * base Field::serializeValue() handles serialization automatically via
 * SeoData's inherited Arrayable (craft\base\Model implements it).
 *
 * See modules/seo/services/SeoResolver.php for how this field's value
 * cascades against section defaults (config/seo.php) and the sitewide
 * `seo` Global Set.
 */
class Seo extends Field
{
    public static function displayName(): string
    {
        return Craft::t('app', 'SEO');
    }

    public static function icon(): string
    {
        return 'search';
    }

    public static function phpType(): string
    {
        return 'array|null';
    }

    public static function dbType(): string
    {
        return Schema::TYPE_JSON;
    }

    /** @var bool Show the title override input. */
    public bool $showTitle = true;

    /** @var bool Show the description override input. */
    public bool $showDescription = true;

    /** @var bool Show the keywords override input. Off by default — meta
     * keywords carry no ranking weight in modern search, kept only for
     * teams that still feed them to internal/legacy tooling. */
    public bool $showKeywords = false;

    /** @var bool Show the OG/Twitter image override input. */
    public bool $showImage = true;

    /** @var bool Show the robots (index/noindex, follow/nofollow) controls. */
    public bool $showRobots = true;

    public function getSettingsHtml(): ?string
    {
        return
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('app', 'Show Title'),
                'id' => 'show-title',
                'name' => 'showTitle',
                'on' => $this->showTitle,
            ]) .
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('app', 'Show Description'),
                'id' => 'show-description',
                'name' => 'showDescription',
                'on' => $this->showDescription,
            ]) .
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('app', 'Show Image'),
                'instructions' => Craft::t('app', 'Overrides the Open Graph/Twitter image for this entry.'),
                'id' => 'show-image',
                'name' => 'showImage',
                'on' => $this->showImage,
            ]) .
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('app', 'Show Robots Controls'),
                'instructions' => Craft::t('app', 'Lets editors mark an individual entry noindex/nofollow.'),
                'id' => 'show-robots',
                'name' => 'showRobots',
                'on' => $this->showRobots,
            ]) .
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('app', 'Show Keywords'),
                'instructions' => Craft::t('app', "Meta keywords aren't used by modern search ranking — off by default."),
                'id' => 'show-keywords',
                'name' => 'showKeywords',
                'on' => $this->showKeywords,
            ]);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if ($value instanceof SeoData) {
            return $value;
        }

        return new SeoData(is_array($value) ? $value : []);
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        Craft::$app->getView()->registerAssetBundle(SeoAsset::class);

        return Craft::$app->getView()->renderTemplate('seo/_input', [
            'id' => $this->getInputId(),
            'name' => $this->handle,
            'value' => $value,
            'field' => $this,
            'element' => $element,
            // What the SERP preview falls back to when the SEO Title input
            // above is empty — same titleFields chain (e.g. heading, then
            // native title) the front end actually renders, not just the
            // raw entry title. See SeoResolver::resolveFallbackTitle().
            'fallbackTitle' => (new SeoResolver())->resolveFallbackTitle($element),
        ]);
    }
}
