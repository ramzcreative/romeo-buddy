<?php

namespace modules\stablestwigextensions\services;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\elements\ElementCollection;
use craft\fields\BaseRelationField;

/**
 * Resolves a page-builder item into a flat set of values, filling gaps from an
 * entry the editor selected and letting the item's own values win.
 *
 * Replaces the getItemData() filter, which had three problems this fixes:
 *
 *   - The field mapping was hardcoded in the module (`image` then `heroImage`),
 *     so a site with differently-named fields had to edit shared code. It now
 *     comes from config/items.php, per site, with per-section overrides.
 *   - Overrides were decided with `?:`, so a legitimate falsy value — 0, "0" —
 *     read as absent and silently fell through to the entry. isEmpty() below is
 *     the single explicit definition.
 *   - Nothing was eager-loaded, so a twelve-card block ran a query per item for
 *     the entry, and another for its image. eagerLoadPaths() gives templates the
 *     list to prime.
 */
class ItemResolver
{
    private array $config;

    public function __construct()
    {
        $this->config = Craft::$app->getConfig()->getConfigFromFile('items');
    }

    /**
     * @return array<string, mixed> The resolved keys, plus `source` (the entry
     *                              used, or null) and `url` (its URL, or null).
     */
    public function resolve(?ElementInterface $item): array
    {
        $keys = $this->config['keys'] ?? [];
        $resolved = array_fill_keys(array_keys($keys), null);
        $resolved['source'] = null;
        $resolved['url'] = null;

        if (!$item instanceof Entry) {
            return $resolved;
        }

        $source = $this->sourceEntry($item);
        $resolved['source'] = $source;
        $resolved['url'] = $source?->getUrl();

        $chains = $this->chainsFor($source);

        foreach ($keys as $key => $map) {
            $own = $map['item'] ? $this->fieldValue($item, $map['item']) : null;

            if (!$this->isEmpty($own)) {
                $resolved[$key] = $this->normalize($own);
                continue;
            }

            if (!$source) {
                continue;
            }

            foreach ($chains[$key] ?? [] as $handle) {
                $value = $this->fieldValue($source, $handle);
                if (!$this->isEmpty($value)) {
                    $resolved[$key] = $this->normalize($value);
                    break;
                }
            }
        }

        return $resolved;
    }

    /**
     * The `with()` paths a template should prime before resolving a set of
     * items, so this doesn't run two queries per card. Built from the same
     * config the resolution uses, so it can't drift out of step with it.
     *
     * @return string[]
     */
    public function eagerLoadPaths(): array
    {
        $sourceField = $this->config['sourceField'] ?? null;
        $paths = [];

        // Only relation fields belong here. A plain text value is already on
        // the element, and with() rejects a handle that isn't relational — so
        // listing `heading` would break the query rather than warm it.
        foreach ($this->config['keys'] ?? [] as $map) {
            if ($map['item'] && $this->isRelation($map['item'])) {
                $paths[] = $map['item'];
            }
        }

        if ($sourceField) {
            $paths[] = $sourceField;

            foreach ($this->config['keys'] ?? [] as $map) {
                foreach ($map['entry'] ?? [] as $handle) {
                    if ($this->isRelation($handle)) {
                        $paths[] = "$sourceField.$handle";
                    }
                }
            }
        }

        return array_values(array_unique($paths));
    }

    private function isRelation(string $handle): bool
    {
        if ($handle === 'title') {
            return false;
        }

        return Craft::$app->getFields()->getFieldByHandle($handle) instanceof BaseRelationField;
    }

    private function sourceEntry(Entry $item): ?Entry
    {
        $handle = $this->config['sourceField'] ?? null;
        if (!$handle) {
            return null;
        }

        // The toggle is a convenience for editors, not the source of truth —
        // an entry that's still selected while the switch is off shouldn't be
        // used, or turning it off would appear to do nothing.
        if ($item->getFieldLayout()?->getFieldByHandle('useEntry') && !$item->getFieldValue('useEntry')) {
            return null;
        }

        $value = $this->firstElement($this->fieldValue($item, $handle));

        // A disabled or deleted entry resolves to nothing rather than blanking
        // the card — the item's own values still render.
        return $value instanceof Entry ? $value : null;
    }

    /** @return array<string, string[]> */
    private function chainsFor(?Entry $source): array
    {
        $chains = [];
        foreach ($this->config['keys'] ?? [] as $key => $map) {
            $chains[$key] = $map['entry'] ?? [];
        }

        $section = $source?->getSection()?->handle;
        if ($section && isset($this->config['sectionKeys'][$section])) {
            foreach ($this->config['sectionKeys'][$section] as $key => $handles) {
                $chains[$key] = $handles;
            }
        }

        return $chains;
    }

    private function fieldValue(Entry $entry, string $handle): mixed
    {
        // `title` is native, not a custom field, so it isn't on the layout.
        if ($handle === 'title') {
            return $entry->title;
        }

        if (!$entry->getFieldLayout()?->getFieldByHandle($handle)) {
            return null;
        }

        return $entry->getFieldValue($handle);
    }

    /**
     * One definition of "the editor didn't fill this in".
     *
     * 0, "0" and false are deliberately NOT empty. They're real values, and
     * treating them as absent is what made the old `?:` version drop a price
     * of zero — the same class of bug that bit the SEO module's Offer builder.
     */
    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        // Relation fields arrive as one of three shapes depending on how the
        // element was loaded: an ElementQuery normally, an ElementCollection
        // once eager-loaded, or a plain array. Handling only the query — as
        // this did first — meant an eager-loaded empty relation read as
        // *populated*, so an item with no image would stop the entry's image
        // being used. Eager loading is the whole point of the cascade
        // performing, so it can't be the path that breaks it.
        if ($value instanceof ElementQuery) {
            return $value->count() === 0;
        }

        if ($value instanceof ElementCollection) {
            return $value->isEmpty();
        }

        if (is_array($value)) {
            return $value === [];
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    /** Relations collapse to the single element a template actually wants. */
    private function normalize(mixed $value): mixed
    {
        return $this->firstElement($value) ?? $value;
    }

    /**
     * The first element out of whichever shape a relation field returned, or
     * null if it isn't a relation at all.
     */
    private function firstElement(mixed $value): ?ElementInterface
    {
        if ($value instanceof ElementInterface) {
            return $value;
        }

        if ($value instanceof ElementQuery) {
            return $value->one();
        }

        if ($value instanceof ElementCollection) {
            return $value->first();
        }

        if (is_array($value) && ($value[0] ?? null) instanceof ElementInterface) {
            return $value[0];
        }

        return null;
    }
}
