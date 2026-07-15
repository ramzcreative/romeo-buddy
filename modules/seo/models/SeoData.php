<?php

namespace modules\seo\models;

use Craft;
use craft\base\Model;
use craft\elements\Asset;

/**
 * The `seo` field's value — one field, one JSON column (Schema::TYPE_JSON,
 * same storage strategy as Craft's own native `craft\fields\Json` field).
 * `craft\base\Model` already implements Yii's Arrayable, so the base
 * `Field::serializeValue()` can serialize this via `toArray()` with no
 * override needed — see modules/seo/fields/Seo.php.
 */
class SeoData extends Model
{
    public ?string $title = null;
    public ?string $description = null;
    public ?string $keywords = null;
    public ?int $imageId = null;
    public ?string $imageDescription = null;

    public bool $noindex = false;
    public bool $nofollow = false;

    public function getImage(): ?Asset
    {
        return $this->imageId ? Craft::$app->getAssets()->getAssetById($this->imageId) : null;
    }

    /**
     * Whether every overridable field is empty — used by SeoResolver to
     * know when to fall through to section/global defaults.
     */
    public function isEmpty(): bool
    {
        return !$this->title && !$this->description && !$this->keywords && !$this->imageId;
    }
}
