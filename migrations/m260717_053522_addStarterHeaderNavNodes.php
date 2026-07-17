<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\elements\Entry;
use justinholt\freenav\FreeNav;

/**
 * Starter Header nodes as "Custom URL" nodes (paste-the-URL, not a real
 * relational link) — a workaround for a bug in justinholtweb/craft-free-nav
 * 5.0.1 where "Entry"-type nodes can't actually be created: the CP's
 * "Select Element" field never gets a picker widget injected, and
 * _submitAddNode() in the plugin's own JS never reads/sends a selected
 * element ID even if it did. Confirmed by reading the plugin's shipped JS
 * (src/resources/js/FreeNavBuilder.js) — no Craft.ElementSelectInput
 * usage anywhere in the bundle. Filed upstream at
 * https://github.com/justinholtweb/craft-freenav. Once that's fixed,
 * these can be converted to real Entry-linked nodes from the CP.
 */
class m260717_053522_addStarterHeaderNavNodes extends Migration
{
    private const URIS = [
        'Home' => '__home__',
        'Books' => 'books',
        'Blog' => 'blog',
        'Shop' => 'shop',
    ];

    public function safeUp(): bool
    {
        $menu = FreeNav::getInstance()->getMenus()->getMenuByHandle('header');
        if (!$menu) {
            throw new \Exception("Couldn't find the 'header' FreeNav menu.");
        }

        $nodeData = [];

        foreach (self::URIS as $title => $uri) {
            $entry = Entry::find()->uri($uri)->one();
            if (!$entry) {
                Craft::warning("Skipping '{$title}' nav node — no entry found with uri '{$uri}'.", __METHOD__);
                continue;
            }

            $nodeData[] = [
                'title' => $title,
                'nodeType' => 'custom',
                'url' => $entry->getUrl(),
            ];
        }

        FreeNav::getInstance()->getNodes()->addNodes($menu, $nodeData);

        return true;
    }

    public function safeDown(): bool
    {
        $menu = FreeNav::getInstance()->getMenus()->getMenuByHandle('header');
        if (!$menu) {
            return true;
        }

        // The header menu was empty before this migration ran (verified),
        // so it's safe to clear it out entirely on rollback rather than
        // tracking which specific nodes this migration created.
        foreach (FreeNav::getInstance()->getNodes()->getNodesByMenuId($menu->id) as $node) {
            Craft::$app->getElements()->deleteElement($node);
        }

        return true;
    }
}
