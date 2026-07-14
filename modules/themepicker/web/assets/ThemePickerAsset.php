<?php

namespace modules\themepicker\web\assets;

use craft\web\AssetBundle;

class ThemePickerAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->css = [
            'theme-picker.css',
        ];

        parent::init();
    }
}
