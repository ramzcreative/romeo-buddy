<?php

namespace modules\iconpicker\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\ThumbableFieldInterface;
use craft\helpers\Cp;
use craft\helpers\Html;
use modules\iconpicker\services\IconRegistry;
use modules\iconpicker\web\assets\IconPickerAsset;
use yii\db\Schema;

/**
 * Icon picker field — a searchable, tabbed grid backed by the icon source
 * configured in config/iconpicker.php. Value is a set-qualified string,
 * e.g. "ui/arrow-left".
 */
class IconPicker extends Field implements ThumbableFieldInterface
{
    public static function displayName(): string
    {
        return Craft::t('app', 'Icon Picker');
    }

    public static function icon(): string
    {
        return 'icons';
    }

    public static function phpType(): string
    {
        return 'string|null';
    }

    public static function dbType(): string
    {
        return Schema::TYPE_STRING;
    }

    /**
     * @var string|null Restrict this field to one set; null shows every
     * set as a tab in the picker.
     */
    public ?string $iconSet = null;

    /** @var string 'small' | 'medium' | 'large' — CP preview size. */
    public string $iconSize = 'medium';

    /** @var bool Show each icon's name under its preview in the picker. */
    public bool $showLabels = false;

    /** @var bool Show the search box in the picker. */
    public bool $enableSearch = true;

    public function getSettingsHtml(): ?string
    {
        $sets = (new IconRegistry())->getSets();

        $setOptions = array_merge(
            [['label' => Craft::t('app', 'All sets (shown as tabs)'), 'value' => '']],
            array_map(fn($s) => ['label' => $s, 'value' => $s], $sets)
        );

        return
            Cp::selectFieldHtml([
                'label' => Craft::t('app', 'Icon Set'),
                'instructions' => Craft::t('app', 'Restrict this field to one set, or show every set as a tab.'),
                'id' => 'icon-set',
                'name' => 'iconSet',
                'options' => $setOptions,
                'value' => $this->iconSet ?? '',
            ]) .
            Cp::selectFieldHtml([
                'label' => Craft::t('app', 'Preview Size'),
                'id' => 'icon-size',
                'name' => 'iconSize',
                'options' => [
                    ['label' => Craft::t('app', 'Small'), 'value' => 'small'],
                    ['label' => Craft::t('app', 'Medium'), 'value' => 'medium'],
                    ['label' => Craft::t('app', 'Large'), 'value' => 'large'],
                ],
                'value' => $this->iconSize,
            ]) .
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('app', 'Show Labels'),
                'instructions' => Craft::t('app', "Show each icon's name under its preview in the picker."),
                'id' => 'show-labels',
                'name' => 'showLabels',
                'on' => $this->showLabels,
            ]) .
            Cp::lightswitchFieldHtml([
                'label' => Craft::t('app', 'Enable Search'),
                'id' => 'enable-search',
                'name' => 'enableSearch',
                'on' => $this->enableSearch,
            ]);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        Craft::$app->getView()->registerAssetBundle(IconPickerAsset::class);

        $registry = new IconRegistry();
        $sets = $this->iconSet ? [$this->iconSet] : $registry->getSets();

        $iconsBySet = [];
        $hasIcons = false;
        foreach ($sets as $set) {
            $icons = $registry->getIcons($set);
            $iconsBySet[$set] = $icons;
            $hasIcons = $hasIcons || count($icons) > 0;
        }

        return Craft::$app->getView()->renderTemplate('iconpicker/_input', [
            'id' => $this->getInputId(),
            'name' => $this->handle,
            'value' => $value,
            'iconsBySet' => $iconsBySet,
            'hasIcons' => $hasIcons,
            'registry' => $registry,
            'iconSize' => $this->iconSize,
            'showLabels' => $this->showLabels,
            'enableSearch' => $this->enableSearch,
        ]);
    }

    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        $svg = (new IconRegistry())->getIconContents($value);

        return $svg ? Html::tag('div', $svg, ['class' => 'iconpicker-preview']) : '';
    }

    public function getThumbHtml(mixed $value, ElementInterface $element, int $size): ?string
    {
        return $this->getPreviewHtml($value, $element) ?: null;
    }
}
