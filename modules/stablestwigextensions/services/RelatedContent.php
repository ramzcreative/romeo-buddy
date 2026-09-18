<?php

namespace modules\stablestwigextensions\services;

use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\ElementCollection;
use craft\elements\Entry;
use Illuminate\Support\Collection;

/**
 * Related entries for a detail page or a `related` block: the editor's picks, topped up from shared topics and then
 * the entry's own section. See config/stables/related.php and docs/business-content-spec.md §5.
 *
 * At most three queries. Every field read is guarded by the layout, so a site without the fields gets an empty list.
 */
class RelatedContent
{
    private array $config;

    public function __construct()
    {
        $config = \modules\support\Config::get('related');
        $this->config = is_array($config) ? $config : [];
    }

    /**
     * @return Entry[]
     */
    public function forEntry(?ElementInterface $entry, ?int $limit = null): array
    {
        if (!$entry instanceof Entry || !$entry->id) {
            return [];
        }

        $limit = $limit ?? $this->limit();
        $list = $this->picks($entry, $entry);
        $list = $this->fromTopics($entry, $list, $limit);

        if ($this->config['fillFromSameSection'] ?? true) {
            $list = $this->fromSection($entry, $list, $limit);
        }

        return $list;
    }

    /**
     * A block's picks; with too few, topped up from its page's topics (never its section: a block is placed on
     * purpose, and "the newest pages" isn't related to anything).
     *
     * @return Entry[]
     */
    public function forBlock(?ElementInterface $block, ?int $limit = null): array
    {
        if (!$block instanceof Entry) {
            return [];
        }

        $page = $this->page($block);
        $list = $this->picks($block, $page);

        return $page ? $this->fromTopics($page, $list, $limit ?? $this->limit()) : $list;
    }

    /**
     * The date a card shows for this entry, per config `dateFields`.
     */
    public function dateFor(?ElementInterface $entry): ?\DateTimeInterface
    {
        if (!$entry instanceof Entry) {
            return null;
        }

        $handle = $this->config['dateFields'][$entry->getSection()?->handle] ?? null;

        if ($handle === 'postDate') {
            return $entry->postDate;
        }

        if (!is_string($handle) || !$entry->getFieldLayout()?->getFieldByHandle($handle)) {
            return null;
        }

        $value = $entry->getFieldValue($handle);

        if (!$value instanceof \DateTimeInterface) {
            return null;
        }

        // An event's own start date is where its SERIES started, which on a card reads as the event being over.
        // The card should say the same thing the listing says: the next date it actually happens on.
        return $handle === 'eventStart' ? ($this->nextOccurrence($entry) ?? $value) : $value;
    }

    /**
     * The next date this event happens on, from the occurrences table — null when it has no upcoming date, which is
     * a finished event, and then its own start date is the honest thing to show.
     *
     * Cancelled dates are skipped, the same way the listing skips them: a card should never advertise one.
     * Everything here tolerates the eventdates module or its table being absent, because a site can have this
     * boilerplate without having run that migration.
     */
    private function nextOccurrence(Entry $entry): ?\DateTimeInterface
    {
        if (!class_exists(\modules\eventdates\services\Occurrences::class)) {
            return null;
        }

        $service = new \modules\eventdates\services\Occurrences();

        if (!$service->hasTable()) {
            return null;
        }

        foreach ($service->forEntry($entry, 'upcoming', 10) as $row) {
            if (!$row['cancelled']) {
                return $row['start'];
            }
        }

        return null;
    }

    /**
     * @return Entry[]
     */
    private function picks(Entry $holder, ?Entry $exclude): array
    {
        $handle = (string)($this->config['field'] ?? 'relatedEntries');

        if (!$holder->getFieldLayout()?->getFieldByHandle($handle)) {
            return [];
        }

        $value = $holder->getFieldValue($handle);

        if ($value instanceof ElementQueryInterface) {
            $items = (clone $value)->with($this->eagerLoadPaths())->all();
        } elseif ($value instanceof Collection) {
            $items = $value->all();
        } else {
            $items = [];
        }

        return $this->unique($items, $exclude ? [$exclude->getCanonicalId()] : []);
    }

    /**
     * @param Entry[] $list
     * @return Entry[]
     */
    private function fromTopics(Entry $entry, array $list, int $limit): array
    {
        $handle = (string)($this->config['topicsField'] ?? '');

        if (count($list) >= $limit || $handle === '' || !$entry->getFieldLayout()?->getFieldByHandle($handle)) {
            return $list;
        }

        $topics = $entry->getFieldValue($handle);
        $topicIds = match (true) {
            $topics instanceof ElementQueryInterface => $topics->ids(),
            $topics instanceof ElementCollection => $topics->ids()->all(),
            default => [],
        };

        if ($topicIds === [] || $this->fillSections() === []) {
            return $list;
        }

        $query = Entry::find()
            ->section($this->fillSections())
            ->relatedTo(['targetElement' => $topicIds, 'field' => $handle]);

        return $this->topUp($query, $entry, $list, $limit);
    }

    /**
     * @param Entry[] $list
     * @return Entry[]
     */
    private function fromSection(Entry $entry, array $list, int $limit): array
    {
        $section = $entry->getSection()?->handle;

        if (count($list) >= $limit || $section === null || !in_array($section, $this->fillSections(), true)) {
            return $list;
        }

        $query = Entry::find()->section($section);
        $criteria = $this->config['sectionCriteria'][$section] ?? [];

        if (is_array($criteria) && $criteria !== []) {
            Craft::configure($query, $criteria);
        }

        return $this->topUp($query, $entry, $list, $limit);
    }

    /**
     * @param Entry[] $list
     * @return Entry[]
     */
    private function topUp(ElementQueryInterface $query, Entry $entry, array $list, int $limit): array
    {
        $excludeIds = [$entry->getCanonicalId(), ...array_map(static fn(Entry $e): int => $e->id, $list)];

        /** @var \craft\elements\db\EntryQuery $query */
        if ($query->orderBy === null || $query->orderBy === []) {
            $query->orderBy(['postDate' => SORT_DESC, 'id' => SORT_DESC]);
        }

        $found = $query
            ->id(['not', ...$excludeIds])
            ->with($this->eagerLoadPaths())
            ->limit($limit - count($list))
            ->all();

        return [...$list, ...$this->unique($found, $excludeIds)];
    }

    /**
     * The block's page: up through nested owners (a block inside a Container) to the entry that isn't in a field.
     */
    private function page(Entry $block): ?Entry
    {
        $owner = $block->getOwner();

        for ($depth = 0; $owner instanceof Entry && $owner->fieldId && $depth < 10; $depth++) {
            $owner = $owner->getOwner();
        }

        return $owner instanceof Entry && !$owner->fieldId ? $owner : null;
    }

    /**
     * @param mixed[] $items
     * @param int[] $excludeIds
     * @return Entry[]
     */
    private function unique(array $items, array $excludeIds): array
    {
        $seen = array_flip($excludeIds);
        $unique = [];

        foreach ($items as $item) {
            if ($item instanceof Entry && !isset($seen[$item->id])) {
                $seen[$item->id] = true;
                $unique[] = $item;
            }
        }

        return $unique;
    }

    /** @return string[] */
    private function fillSections(): array
    {
        return array_values(array_filter((array)($this->config['fillSections'] ?? []), 'is_string'));
    }

    private function limit(): int
    {
        return max(1, (int)($this->config['limit'] ?? 3));
    }

    /** @return string[] */
    private function eagerLoadPaths(): array
    {
        return (new ItemResolver())->entryEagerLoadPaths();
    }
}
