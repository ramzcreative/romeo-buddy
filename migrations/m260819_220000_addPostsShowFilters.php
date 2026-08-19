<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Lightswitch;

/**
 * Adds "Show Filters" to the posts block, and retires the postType option that
 * has no template behind it.
 *
 * Two changes to the same entry type, so they ship together:
 *
 * 1. `showFilters` — lets an editor choose per block whether the listing gets
 *    its filter controls. On the blog that also picks which of the two
 *    navigation styles renders: filters (in-place, query-string URLs) or the
 *    category nav (links out to the indexable category pages). They are
 *    alternatives, not companions — showing both offers two routes to the same
 *    content, one of which config/stables/seo.php's robots rule tells search
 *    engines to ignore. It was a hardcoded template flag; it belongs to the
 *    editor.
 *
 * 2. `news` comes off the postType dropdown. `_blocks/posts.twig` includes
 *    `_sections/<postType>` and there is no `_sections/news` and no news
 *    section — so selecting it threw a TemplateLoaderException, i.e. a 500 on
 *    a live page, one click away in the CP. The template also gets an
 *    `ignore missing`, but removing the option is the actual fix: an option
 *    that renders nothing is not better than no option.
 *
 * Additive apart from the dropdown option, so it reaches the same result
 * whichever of project config or this migration lands first.
 */
class m260819_220000_addPostsShowFilters extends Migration
{
    public function safeUp(): bool
    {
        $fields = Craft::$app->getFields();
        $entries = Craft::$app->getEntries();

        // 1. The field.
        if (!$fields->getFieldByHandle('showFilters')) {
            $field = new Lightswitch([
                'name' => 'Show Filters',
                'handle' => 'showFilters',
                'instructions' => 'Show the filter controls above this listing. On the blog, turning this off shows the category navigation instead.',
                'default' => true,
            ]);

            if (!$fields->saveField($field)) {
                throw new \Exception("Couldn't save 'showFilters': " . implode(', ', $field->getErrorSummary(true)));
            }
        }

        $postsType = $entries->getEntryTypeByHandle('posts');
        if (!$postsType) {
            throw new \Exception("The 'posts' entry type is missing.");
        }

        $layout = $postsType->getFieldLayout();
        if (!$layout->getFieldByHandle('showFilters')) {
            $tabs = $layout->getTabs();
            $tab = $tabs[0] ?? null;
            if (!$tab) {
                throw new \Exception('The posts field layout has no tabs.');
            }

            // setTabs() before setElements() — a tab not yet attached to its
            // layout throws "Field layout tab is missing its field layout."
            $layout->setTabs($tabs);
            $tab->setElements(array_merge($tab->getElements(), [
                new CustomField($fields->getFieldByHandle('showFilters')),
            ]));
            $postsType->setFieldLayout($layout);

            if (!$entries->saveEntryType($postsType)) {
                throw new \Exception("Couldn't add 'showFilters' to posts: " . implode(', ', $postsType->getErrorSummary(true)));
            }
        }

        // 2. Retire the `news` option.
        $postType = $fields->getFieldByHandle('postType');
        if ($postType && property_exists($postType, 'options')) {
            $options = $postType->options;
            $kept = array_values(array_filter($options, fn($o) => ($o['value'] ?? null) !== 'news'));

            if (count($kept) !== count($options)) {
                // Nothing may be left pointing at it. A block still set to
                // `news` would render nothing rather than 500 once posts.twig
                // has `ignore missing`, but it should be corrected, not hidden.
                $inUse = (new \craft\db\Query())
                    ->from('{{%elements_sites}}')
                    ->where(['like', 'content', '"news"'])
                    ->count();

                $postType->options = $kept;

                if (!$fields->saveField($postType)) {
                    throw new \Exception("Couldn't update postType: " . implode(', ', $postType->getErrorSummary(true)));
                }

                echo "    > removed the 'news' postType option"
                    . ($inUse ? " (note: $inUse content row(s) mention \"news\" — check them)" : '')
                    . "\n";
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        $fields = Craft::$app->getFields();

        if ($field = $fields->getFieldByHandle('showFilters')) {
            $fields->deleteField($field);
        }

        // The `news` option is deliberately not restored — it never had a
        // template behind it.
        return true;
    }
}
