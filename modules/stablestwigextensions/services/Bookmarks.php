<?php

namespace modules\stablestwigextensions\services;

use craft\elements\Entry;
use craft\helpers\Html;
use craft\helpers\StringHelper;

/**
 * The `id` a block wears when an editor bookmarks it, so a link can point at
 * `#read-this-section`.
 *
 * What an editor types is never what reaches the DOM. They write "Read This
 * Section!", or "#pricing", or the same thing on two blocks — all three are
 * ordinary, none of them is a valid unique id, and the page is what breaks.
 * So the value is slugified here, a leading `#` is dropped, and a second
 * block asking for an id already used on this page gets `-2`.
 *
 * Resolved once per block per request: a template can ask for the id and the
 * attribute (hero/standard does), and both have to be the same answer rather
 * than the second one counting as a collision with the first.
 */
class Bookmarks
{
    /** @var array<int, string|null> Resolved id per block element ID. */
    private array $resolved = [];

    /** @var array<string, true> Every id handed out on this page. */
    private array $taken = [];

    /**
     * The id for this block, or null when it hasn't been bookmarked (or the
     * field isn't on it — a theme can hide it, and a block from before the
     * field existed never had it).
     */
    public function id(?Entry $block): ?string
    {
        if (!$block || !$block->id) {
            return null;
        }

        if (array_key_exists($block->id, $this->resolved)) {
            return $this->resolved[$block->id];
        }

        return $this->resolved[$block->id] = $this->resolve($block);
    }

    /** ` id="..."` for a bookmarked block, or an empty string — safe to drop into any tag. */
    public function attr(?Entry $block): string
    {
        $id = $this->id($block);

        return $id === null ? '' : ' ' . Html::renderTagAttributes(['id' => $id]);
    }

    private function resolve(Entry $block): ?string
    {
        if (!$block->getFieldLayout()?->getFieldByHandle('bookmark')) {
            return null;
        }

        $slug = StringHelper::slugify(ltrim(trim((string)$block->getFieldValue('bookmark')), '#'));

        if ($slug === '') {
            return null;
        }

        // An id can't start with a digit in CSS selectors (`#2024-results` is a
        // parse error), and "2024 results" is a thing an editor will type.
        if (ctype_digit($slug[0])) {
            $slug = 'section-' . $slug;
        }

        $id = $slug;
        $n = 1;

        while (isset($this->taken[$id])) {
            $id = $slug . '-' . ++$n;
        }

        $this->taken[$id] = true;

        return $id;
    }
}
