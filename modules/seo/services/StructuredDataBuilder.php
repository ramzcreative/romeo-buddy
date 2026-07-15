<?php

namespace modules\seo\services;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Entry;
use craft\models\Section;

/**
 * Assembles the JSON-LD @graph for a page. Each build*() method returns
 * null when it has nothing meaningful to say (no org name set, no address
 * on file, no commerce fields on this entry, etc.) — build() filters those
 * out, so the graph degrades gracefully instead of emitting empty/fake
 * structured data.
 */
class StructuredDataBuilder
{
    private SeoResolver $resolver;

    public function __construct(?SeoResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new SeoResolver();
    }

    public function build(?ElementInterface $entry = null): array
    {
        return array_values(array_filter([
            $this->buildOrganization(),
            $this->buildWebSite(),
            $this->buildBreadcrumbList($entry),
            $this->buildArticle($entry),
            $this->buildProduct($entry),
            $this->buildLocalBusiness(),
        ]));
    }

    private function siteUrl(): string
    {
        $baseUrl = Craft::$app->getSites()->getCurrentSite()->getBaseUrl() ?? '';
        return rtrim($baseUrl, '/');
    }

    public function buildOrganization(): ?array
    {
        $orgName = $this->resolver->getOrgName();
        if (!$orgName) {
            return null;
        }

        $data = [
            '@type' => 'Organization',
            '@id' => $this->siteUrl() . '/#organization',
            'name' => $orgName,
            'url' => $this->siteUrl(),
        ];

        if ($logo = $this->resolver->getLogo()) {
            $data['logo'] = [
                '@type' => 'ImageObject',
                'url' => $logo->getUrl(),
            ];
        }

        $sameAs = $this->resolver->getSocialLinks();
        if ($sameAs) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    public function buildWebSite(): array
    {
        $site = Craft::$app->getSites()->getCurrentSite();

        // Confirmed real route — themes/_base/templates/search.twig reads
        // craft.app.request.getQueryParam('q') on /search/.
        return [
            '@type' => 'WebSite',
            '@id' => $this->siteUrl() . '/#website',
            'url' => $this->siteUrl(),
            'name' => $site->getName(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->siteUrl() . '/search/?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public function buildBreadcrumbList(?ElementInterface $entry): ?array
    {
        if (!$entry instanceof Entry) {
            return null;
        }

        // The homepage itself isn't a meaningful breadcrumb trail (just
        // "Home > Home").
        if ($entry->uri === '__home__' || $entry->uri === '') {
            return null;
        }

        $section = $entry->getSection();
        if (!$section) {
            return null;
        }

        $position = 1;
        $items = [[
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => 'Home',
            'item' => $this->siteUrl() . '/',
        ]];

        if ($section->type === Section::TYPE_STRUCTURE) {
            // pages is a structure section — real parent hierarchy is free.
            // Skip the homepage if it's ever a literal structural ancestor —
            // "Home" is already added above, don't list it twice.
            foreach ($entry->getAncestors()->all() as $ancestor) {
                if ($ancestor->uri === '__home__') {
                    continue;
                }
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $ancestor->title,
                    'item' => $ancestor->getUrl(),
                ];
            }
        } elseif ($section->handle === 'blog') {
            // blog is a flat channel section — synthesize Home -> Blog index -> post.
            $blogIndex = Entry::find()->section('pages')->uri('blog')->one();
            if ($blogIndex) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $blogIndex->title,
                    'item' => $blogIndex->getUrl(),
                ];
            }
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $entry->title,
            'item' => $entry->getUrl(),
        ];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    public function buildArticle(?ElementInterface $entry): ?array
    {
        if (!$entry instanceof Entry || $entry->getSection()?->handle !== 'blog') {
            return null;
        }

        $data = [
            '@type' => 'BlogPosting',
            'headline' => $entry->title,
            'url' => $entry->getUrl(),
            'mainEntityOfPage' => $entry->getUrl(),
        ];

        if ($entry->postDate) {
            $data['datePublished'] = $entry->postDate->format(DATE_ATOM);
        }
        if ($entry->dateUpdated) {
            $data['dateModified'] = $entry->dateUpdated->format(DATE_ATOM);
        }

        $excerpt = $entry->getFieldValue('excerpt');
        if (is_string($excerpt) && trim(strip_tags($excerpt)) !== '') {
            $data['description'] = trim(strip_tags($excerpt));
        }

        $image = $entry->getFieldValue('image');
        $imageAsset = $image && method_exists($image, 'one') ? $image->one() : null;
        if ($imageAsset) {
            $data['image'] = $imageAsset->getUrl();
        }

        if ($orgName = $this->resolver->getOrgName()) {
            $data['publisher'] = [
                '@type' => 'Organization',
                'name' => $orgName,
            ];
        }

        return $data;
    }

    /**
     * Dormant until a commerce section/field shape actually exists — no
     * such section is configured today, so this checks for a `price` field
     * on the entry's own layout rather than hardcoding a section handle
     * that doesn't exist yet.
     */
    public function buildProduct(?ElementInterface $entry): ?array
    {
        if (!$entry instanceof Entry) {
            return null;
        }

        $layout = $entry->getFieldLayout();
        if (!$layout || !$layout->getFieldByHandle('price')) {
            return null;
        }

        $price = $entry->getFieldValue('price');
        if ($price === null || $price === '') {
            return null;
        }

        return [
            '@type' => 'Product',
            'name' => $entry->title,
            'url' => $entry->getUrl(),
            'offers' => [
                '@type' => 'Offer',
                'price' => (string)$price,
                'priceCurrency' => 'USD',
                'availability' => 'https://schema.org/InStock',
            ],
        ];
    }

    /**
     * Reuses the existing `general` Global Set's Addresses field (structured
     * PostalAddress + lat/long, better than a hand-typed string) — see
     * README/plan for why this isn't duplicated in the `seo` Global Set.
     */
    public function buildLocalBusiness(): ?array
    {
        $general = Craft::$app->getGlobals()->getSetByHandle('general');
        if (!$general) {
            return null;
        }

        $addressQuery = $general->address ?? null;
        $address = $addressQuery && method_exists($addressQuery, 'one') ? $addressQuery->one() : null;
        if (!$address) {
            return null;
        }

        $data = [
            '@type' => 'LocalBusiness',
            'name' => $this->resolver->getOrgName() ?? Craft::$app->getSites()->getCurrentSite()->getName(),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => trim(($address->addressLine1 ?? '') . ' ' . ($address->addressLine2 ?? '')),
                'addressLocality' => $address->locality ?? '',
                'addressRegion' => $address->administrativeArea ?? '',
                'postalCode' => $address->postalCode ?? '',
                'addressCountry' => $address->countryCode ?? '',
            ],
        ];

        if (!empty($address->latitude) && !empty($address->longitude)) {
            $data['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => $address->latitude,
                'longitude' => $address->longitude,
            ];
        }

        if (!empty($general->phone)) {
            $data['telephone'] = $general->phone;
        }

        return $data;
    }
}
