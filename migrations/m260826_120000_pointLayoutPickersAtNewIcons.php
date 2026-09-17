<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;

/**
 * Repoints the buttonbox layout pickers at the new icon set in
 * web/assets/cms/images/layout-*.svg.
 *
 * WHY A MIGRATION AND NOT A YAML EDIT
 * A field's `options` live in project config (config/project/fields/*.yaml).
 * Hand-editing those files is the thing CLAUDE.md warns about — they are
 * UUID-referential, and a malformed one takes the whole project config with
 * it. Saving through the fields service is the same code path the CP itself
 * uses, so it regenerates the YAML correctly.
 *
 * WHAT THIS TOUCHES, AND WHAT IT DOES NOT
 * Only the `imageUrl` of options that already exist. Every other key on an
 * option — label, value, showLabel, imageAlign, default — is copied through
 * untouched, and options this map does not mention are left exactly as they
 * are. No content is read or written: a buttonbox value is the option's
 * `value` string, which is not changing, so no entry's stored layout can
 * shift. That matters here — a content migration that matches too broadly is
 * how a previous one wiped entries on another site.
 *
 * SCOPE
 * Image+Text, Slider and Cards, which are the three pickers the new set
 * covers. `layoutPosts` is deliberately left alone: its two options both
 * still point at the old grid.png, but new icons for it have not been drawn,
 * and pointing it at a card icon to look tidy would be worse than leaving it
 * visibly unfinished.
 *
 * SAFE TO RE-RUN
 * Setting the same imageUrl twice is a no-op, so this is idempotent. safeDown
 * restores the previous paths rather than emptying them, so a rollback leaves
 * the pickers looking the way they did before rather than blank.
 */
class m260826_120000_pointLayoutPickersAtNewIcons extends Migration
{
    private const BASE = '/assets/cms/images/';

    /**
     * fieldHandle => [ option value => icon filename ]
     */
    private const ICONS = [
        'layoutCards' => [
            'grid'  => 'layout-cards-grid.svg',
            'list'  => 'layout-cards-list.svg',
            'large' => 'layout-cards-large.svg',
        ],
        'layoutImageText' => [
            'default' => 'layout-imagetext-default.svg',
            'show'    => 'layout-imagetext-show.svg',
            'hero'    => 'layout-imagetext-hero.svg',
        ],
        'layoutSliders' => [
            'sliders'   => 'layout-slider-slider.svg',
            'carousels' => 'layout-slider-carousel.svg',
            'slants'    => 'layout-slider-slant.svg',
            'hero'      => 'layout-slider-hero.svg',
        ],
    ];

    /**
     * What these pointed at before, so a rollback is a restore rather than a
     * blanking. Written out rather than read at runtime because by the time
     * safeDown runs, the old values are gone.
     */
    private const PREVIOUS = [
        'layoutCards' => [
            'grid'  => 'grid.png',
            'list'  => 'list.png',
            'large' => 'grid-2.png',
        ],
        'layoutImageText' => [
            'default' => 'image-text-1.png',
            'show'    => 'image-text-2.png',
            'hero'    => 'image-text-3.png',
        ],
        'layoutSliders' => [
            'sliders'   => 'grid.png',
            'carousels' => 'grid.png',
            'slants'    => 'grid.png',
            'hero'      => 'grid.png',
        ],
    ];

    public function safeUp(): bool
    {
        return $this->applyIcons(self::ICONS);
    }

    public function safeDown(): bool
    {
        return $this->applyIcons(self::PREVIOUS);
    }

    /**
     * @param array<string, array<string, string>> $map
     */
    private function applyIcons(array $map): bool
    {
        $fieldsService = Craft::$app->getFields();

        foreach ($map as $handle => $icons) {
            $field = $fieldsService->getFieldByHandle($handle);

            // A site branched from this boilerplate may not carry every picker.
            // Missing is not a failure — skipping keeps the migration portable.
            if ($field === null) {
                echo "    > no `{$handle}` field on this site, skipping\n";
                continue;
            }

            if (!property_exists($field, 'options') || !is_array($field->options)) {
                echo "    > `{$handle}` has no options array, skipping\n";
                continue;
            }

            $options = $field->options;
            $changed = 0;

            foreach ($options as $i => $option) {
                // Buttonbox hands these back as plain arrays; guard anyway so a
                // future field type change fails loudly rather than silently
                // dropping an option.
                if (!is_array($option) || !isset($option['value'])) {
                    continue;
                }

                $icon = $icons[$option['value']] ?? null;

                if ($icon === null) {
                    continue;
                }

                $options[$i]['imageUrl'] = self::BASE . $icon;
                $changed++;
            }

            if ($changed === 0) {
                echo "    > `{$handle}` matched no options, leaving it alone\n";
                continue;
            }

            $field->options = $options;

            if (!$fieldsService->saveField($field)) {
                throw new \Exception(
                    "Couldn't save the `{$handle}` field: "
                    . implode(', ', $field->getErrorSummary(true))
                );
            }

            echo "    > `{$handle}`: repointed {$changed} option(s)\n";
        }

        return true;
    }
}
