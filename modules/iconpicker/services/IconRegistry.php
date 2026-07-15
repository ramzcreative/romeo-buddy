<?php

namespace modules\iconpicker\services;

use Craft;
use enshrined\svgSanitize\Sanitizer;

/**
 * Resolves the configured icon source (config/iconpicker.php) into sets,
 * icon lists, and sanitized SVG content. Used by both the CP field
 * (modules\iconpicker\fields\IconPicker) and the front-end renderIcon()
 * Twig function, so sanitization happens in exactly one place.
 */
class IconRegistry
{
    private array $settings;

    public function __construct()
    {
        $this->settings = Craft::$app->getConfig()->getConfigFromFile('iconpicker');
    }

    public function getIconsPath(): string
    {
        return Craft::getAlias($this->settings['iconsPath'] ?? '@webroot/dist/assets/icons');
    }

    private function isCacheEnabled(): bool
    {
        return (bool)($this->settings['enableCache'] ?? false);
    }

    private function getCacheDuration(): ?int
    {
        return $this->settings['cacheDuration'] ?? null;
    }

    /**
     * Top-level subfolder names under the icons path — each becomes a
     * named "set" shown as a tab in the picker.
     *
     * @return string[]
     */
    public function getSets(): array
    {
        return $this->withCache('sets', function () {
            $path = $this->getIconsPath();
            if (!is_dir($path)) {
                return [];
            }

            $sets = [];
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (is_dir($path . '/' . $entry)) {
                    $sets[] = $entry;
                }
            }
            sort($sets);

            return $sets;
        });
    }

    /**
     * @param string|null $set Restrict to one set, or null for every set.
     * @return array<string, string> ["set/icon-name" => "/resolved/path.svg"]
     */
    public function getIcons(?string $set = null): array
    {
        return $this->withCache('icons.' . ($set ?? '_all'), function () use ($set) {
            $sets = $set !== null ? [$set] : $this->getSets();
            $basePath = $this->getIconsPath();

            $icons = [];
            foreach ($sets as $s) {
                $setPath = $basePath . '/' . $s;
                if (!is_dir($setPath)) {
                    continue;
                }
                foreach (glob($setPath . '/*.svg') ?: [] as $file) {
                    $name = basename($file, '.svg');
                    $icons["$s/$name"] = $file;
                }
            }
            ksort($icons);

            return $icons;
        });
    }

    public function iconExists(string $key): bool
    {
        [$set, $name] = array_pad(explode('/', $key, 2), 2, null);
        if ($set === null || $name === null) {
            return false;
        }

        return is_file($this->getIconsPath() . '/' . $set . '/' . $name . '.svg');
    }

    /**
     * Reads and sanitizes an icon's SVG content by its "set/name" key.
     * Returns null if the icon doesn't exist or fails to parse as SVG —
     * callers should degrade gracefully (render nothing), not error.
     */
    public function getIconContents(string $key): ?string
    {
        return $this->withCache('content.' . $key, function () use ($key) {
            [$set, $name] = array_pad(explode('/', $key, 2), 2, null);
            if ($set === null || $name === null) {
                return null;
            }

            $path = $this->getIconsPath() . '/' . $set . '/' . $name . '.svg';
            if (!is_file($path)) {
                return null;
            }

            $raw = file_get_contents($path);
            if ($raw === false) {
                return null;
            }

            $sanitizer = new Sanitizer();
            $sanitizer->removeRemoteReferences(true);
            // These are inlined directly into the page (CP picker + front-end
            // renderIcon()) — an XML declaration is invalid mid-document, and
            // Craft's config/general.php already sets the doctype/charset.
            $sanitizer->removeXMLTag(true);
            $sanitizer->minify(true);
            $clean = $sanitizer->sanitize($raw);

            return $clean !== false ? trim($clean) : null;
        });
    }

    private function withCache(string $key, callable $callback): mixed
    {
        if (!$this->isCacheEnabled()) {
            return $callback();
        }

        return Craft::$app->getCache()->getOrSet('iconpicker.' . $key, $callback, $this->getCacheDuration());
    }
}
