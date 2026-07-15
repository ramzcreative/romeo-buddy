<?php

namespace modules\iconpicker\web\assets;

use craft\web\assets\garnish\GarnishAsset;
use craft\web\AssetBundle;

class IconPickerAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [
            GarnishAsset::class,
        ];
        $this->css = [
            'icon-picker.css',
        ];
        $this->js = [
            'icon-picker.js',
        ];

        parent::init();
    }
}
