<?php

namespace modules\seo\web\assets;

use craft\web\AssetBundle;

class SeoAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->css = [
            'seo-field.css',
        ];
        $this->js = [
            'seo-field.js',
        ];

        parent::init();
    }
}
