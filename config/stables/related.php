<?php
/**
 * Related content: the `relatedEntries` field, the `related` block and the
 * "Related" list on detail pages. See docs/business-content-spec.md §5 and
 * modules/stablestwigextensions/services/RelatedContent.php.
 *
 * A list is built in order until it holds `limit` entries:
 *   1. the editor's picks (all of them, even past the limit)
 *   2. entries sharing a topic, from `fillSections` (once the site has `topicsField`)
 *   3. the newest in the entry's own section, when it's in `fillSections`
 * The entry itself and anything already listed are never repeated.
 *
 * - sections: what editors can pick. The migration that creates the field
 *   reads this once; after that, change the field's sources in the CP.
 * - sectionCriteria: extra query params for step 3, per section, so an event
 *   page doesn't fill up with past events. Any entry query param works,
 *   including orderBy.
 * - dateFields: the date a card shows, per section. Sections not listed show none.
 */

return [
    '*' => [
        'field' => 'relatedEntries',
        'topicsField' => 'topics',
        'sections' => ['blog', 'books', 'events', 'jobs', 'pages'],
        'fillSections' => ['blog', 'books', 'events', 'jobs'],
        'fillFromSameSection' => true,
        'limit' => 3,
        'sectionCriteria' => [
            'events' => ['eventStart' => '>= now', 'orderBy' => 'eventStart asc'],
            'jobs' => ['jobValidThrough' => ['or', ':empty:', '>= today']],
        ],
        'dateFields' => [
            'blog' => 'postDate',
            'books' => 'postDate',
            'events' => 'eventStart',
        ],
    ],
];
