<?php
namespace modules\stablestwigextensions;

use modules\stablestwigextensions\services\AdminBar;
use modules\stablestwigextensions\services\BlockFieldCss;
use modules\stablestwigextensions\services\ItemResolver;
use modules\stablestwigextensions\twigextensions\ModuleTwigExtensions;
use modules\themepicker\services\BlockRules;
use modules\themepicker\services\ThemeConfig;
use modules\themepicker\services\VariantContext;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\elements\Entry;
use craft\events\DefineEntryTypesForFieldEvent;
use craft\events\DefineInputOptionsEvent;
use craft\fields\BaseOptionsField;
use craft\fields\Dropdown;
use craft\fields\data\SingleOptionFieldData;
use modules\themepicker\services\ThemeRegistry;
use craft\fields\Matrix;
use craft\web\Application;
use yii\base\Event;

class Module extends \yii\base\Module
{
    public function init()
    {
        // Define a custom alias named after the namespace
        Craft::setAlias('@stablestwigextensions', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request.
        // Must include the "modules\" root (matching this class's own namespace above) —
        // without it, Yii can't resolve a controller ID to a class at all.
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'modules\\stablestwigextensions\\console\\controllers';
        } else {
            $this->controllerNamespace = 'modules\\stablestwigextensions\\controllers';
        }

        parent::init();

        // What a theme may change — see craft-modules' ThemeConfig.
        ThemeConfig::register('blockfields', BlockRules::themeUnits());
        ThemeConfig::register('items', ItemResolver::themeUnits());

        // The posts block's Section picker offers a publishing section only when it exists and the theme has its
        // listing template (`_sections/<handle>/index`), the same "a template exists" rule as blocks. A value already
        // saved stays in the list, so an existing block never loses its choice by being opened.
        Event::on(Dropdown::class, BaseOptionsField::EVENT_DEFINE_OPTIONS, function(DefineInputOptionsEvent $event) {
            if ($event->sender->handle !== 'postType') {
                return;
            }

            $saved = $event->value instanceof SingleOptionFieldData ? $event->value->value : null;
            $roots = [];

            $theme = ThemeConfig::currentThemeHandle();

            foreach ($theme !== null ? (new ThemeRegistry())->chain($theme) : [] as $handle) {
                $roots[] = Craft::getAlias('@root/themes/' . $handle . '/templates');
            }

            $roots[] = Craft::getAlias('@root/themes/_base/templates');

            $event->options = array_values(array_filter($event->options, static function(array $option) use ($saved, $roots) {
                $handle = $option['value'] ?? null;

                if ($handle === null || $handle === $saved) {
                    return true;
                }

                if (!Craft::$app->getEntries()->getSectionByHandle($handle)) {
                    return false;
                }

                foreach ($roots as $root) {
                    if (is_file("{$root}/_sections/{$handle}/index.twig")) {
                        return true;
                    }
                }

                return false;
            }));
        });

        // A logo's name is its alt text (business blocks spec §4.3), so a Logo Strip with an unnamed logo doesn't
        // publish. Live saves only: drafts and autosaves still go through.
        Event::on(Entry::class, Model::EVENT_AFTER_VALIDATE, function(Event $event) {
            /** @var Entry $entry */
            $entry = $event->sender;

            if ($entry->getScenario() !== Element::SCENARIO_LIVE || $entry->getType()->handle !== 'logos' || !$entry->getFieldLayout()?->getFieldByHandle('logos')) {
                return;
            }

            $unnamed = array_filter($entry->getFieldValue('logos')->all(), static fn($asset) => trim((string)$asset->alt) === '');

            if ($unnamed) {
                $entry->addError('field:logos', Craft::t('app', 'Add alt text (the company name) to: {files}.', [
                    'files' => implode(', ', array_map(static fn($asset) => $asset->filename, $unnamed)),
                ]));
            }
        });

        // Per-block field visibility in the page builder. Generated rather
        // than a static file because it has to embed per-database entry type
        // ids — see services/BlockFieldCss.php. On EVENT_INIT, not here: this
        // module bootstraps before theme-picker, and the rules follow the
        // CP site's theme.
        if (Craft::$app->getRequest()->getIsCpRequest()) {
            Event::on(Application::class, Application::EVENT_INIT, function() {
                // Cached; see BlockFieldCss::cpPayload() for what the key covers.
                $payload = (new BlockFieldCss())->cpPayload();
                $view = Craft::$app->getView();

                if ($payload['css'] !== '') {
                    $view->registerCss($payload['css']);
                }

                // The type dropdown can't be restricted in CSS: Garnish appends an
                // open disclosure menu to document.body, so it isn't a descendant
                // of the form being edited and nothing scopes it to the block's
                // type. See resources/js/blockSwitchGroups.js.
                if ($payload['switchGroups']) {
                    $view->registerJs(
                        'window.stablesSwitchGroups = ' . \craft\helpers\Json::encode($payload['switchGroups']) . ';',
                        \yii\web\View::POS_HEAD
                    );
                    $view->registerJs(
                        @file_get_contents(__DIR__ . '/resources/js/blockSwitchGroups.js') ?: '',
                        \yii\web\View::POS_END
                    );
                }

                // Blocks the theme doesn't offer: cards-view Add menus in JS (their items carry no type id), see
                // resources/js/blockAvailability.js.
                if ($payload['menuLabels']) {
                    $view->registerJs(
                        'window.stablesUnofferedBlocks = ' . \craft\helpers\Json::encode($payload['menuLabels']) . ';',
                        \yii\web\View::POS_HEAD
                    );
                    $view->registerJs(
                        @file_get_contents(__DIR__ . '/resources/js/blockAvailability.js') ?: '',
                        \yii\web\View::POS_END
                    );
                }

                // Fields hidden on blocks nested inside a parent (`nested` rules): only a script can tell which block
                // owns a field two levels down, e.g. inside a Columns block's column. See resources/js/blockNestedFields.js.
                if ($payload['nested']['rules'] ?? []) {
                    $view->registerJs(
                        'window.stablesNestedFields = ' . \craft\helpers\Json::encode($payload['nested']) . ';',
                        \yii\web\View::POS_HEAD
                    );
                    $view->registerJs(
                        @file_get_contents(__DIR__ . '/resources/js/blockNestedFields.js') ?: '',
                        \yii\web\View::POS_END
                    );
                }

                // ...and blocks-view fields on the server. Validation doesn't read this event, so a page that already
                // holds such a block still saves. Never removes every type: Craft throws when none are left.
                if ($payload['unoffered']) {
                    $unoffered = array_flip($payload['unoffered']);
                    $builderFields = array_flip($payload['builderFields']);

                    Event::on(Matrix::class, Matrix::EVENT_DEFINE_ENTRY_TYPES, function(DefineEntryTypesForFieldEvent $event) use ($unoffered, $builderFields) {
                        if (!isset($builderFields[$event->sender->handle])) {
                            return;
                        }

                        $offered = array_filter($event->entryTypes, fn($type) => !isset($unoffered[$type->handle]));

                        if ($offered) {
                            $event->entryTypes = array_values($offered);
                        }
                    });
                }
            });
        }

        // Custom initialization code goes here...
        if (Craft::$app->getRequest()->getIsSiteRequest()) {
            // Instantiate + register the extension:
            $extension = new ModuleTwigExtensions();
            Craft::$app->getView()->registerTwigExtension($extension);

            // Previewing a variant from the admin bar previews its VALUES too, not just its colours: a block
            // whose background differs under that variant has to show that difference, or the preview is a lie
            // and an inline edit made in it would be aimed at the wrong slot.
            // craft-modules docs/per-variant-values-spec.md §4.
            if (!Craft::$app->getUser()->getIsGuest()) {
                $previewed = (new AdminBar())->previewedPageTheme();

                if ($previewed !== null) {
                    VariantContext::preview($previewed['handle']);
                }
            }
        }
    }
}