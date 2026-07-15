<?php

namespace modules\iconpicker\twig;

use modules\iconpicker\services\IconRegistry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes renderIcon() to front-end templates — resolves + sanitizes the
 * same way the CP field's picker does (IconRegistry::getIconContents()),
 * so there's exactly one place sanitization can be forgotten, not two.
 */
class IconPickerTwigExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('renderIcon', [$this, 'renderIcon'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Renders a "set/icon-name" value as inline, sanitized SVG markup.
     * Returns an empty string if the value is empty or doesn't resolve to
     * an icon — degrades gracefully rather than erroring (an icon picked
     * under one config may not exist under another).
     */
    public function renderIcon(?string $value): string
    {
        if (!$value) {
            return '';
        }

        return (new IconRegistry())->getIconContents($value) ?? '';
    }
}
