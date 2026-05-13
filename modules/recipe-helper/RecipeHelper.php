<?php

namespace MattBloomfield\RecipeHelper;

use Craft;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\ModelEvent;
use craft\events\RegisterCpNavItemsEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;
use craft\web\View;
use MattBloomfield\RecipeHelper\services\NutritionService;
use MattBloomfield\RecipeHelper\twig\RecipeFractionConverter;
use yii\base\Event;
use yii\base\Module as BaseModule;

/**
 * RecipeHelper module
 *
 * @method static RecipeHelper getInstance()
 */
class RecipeHelper extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@MattBloomfield/RecipeHelper', __DIR__);

        // Set the controllerNamespace based on whether this is a console or web request
        if (Craft::$app->request->isConsoleRequest) {
            $this->controllerNamespace = 'MattBloomfield\\RecipeHelper\\console\\controllers';
        } else {
            $this->controllerNamespace = 'MattBloomfield\\RecipeHelper\\controllers';
        }

        parent::init();

        $this->attachEventHandlers();

        // Any code that creates an element query or loads Twig should be deferred until
        // after Craft is fully initialized, to avoid conflicts with other plugins/modules
        Craft::$app->onInit(function() {
            Craft::$app->view->registerTwigExtension(new RecipeFractionConverter());
        });
    }

    private function attachEventHandlers(): void
    {
        // Register CP nav item
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            function(RegisterCpNavItemsEvent $event) {
                $event->navItems[] = [
                    'url' => 'recipe-import',
                    'label' => 'Recipe Import',
                    'icon' => 'arrow-down-to-bracket',
                ];
            }
        );

        // Register CP URL rules
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['recipe-import'] = 'recipe-helper/import/index';
            }
        );

        // Auto-calculate nutrition on recipe save when ingredients change
        Event::on(
            Entry::class,
            Entry::EVENT_BEFORE_SAVE,
            function(ModelEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                // Only recipe entries, only canonical saves (not drafts/autosaves)
                if ($entry->type->handle !== 'recipe' || $entry->getIsDraft() || $entry->propagating || $entry->resaving) {
                    return;
                }

                $service = new NutritionService();

                // Check for force flag (set by the sidebar button)
                $force = !Craft::$app->getRequest()->getIsConsoleRequest()
                    && Craft::$app->getRequest()->getBodyParam('forceNutrition');

                if ($force || $service->needsRecalculation($entry)) {
                    $result = $service->calculate($entry);
                    if (!$result['success']) {
                        Craft::warning("Nutrition calculation failed: {$result['message']}", __METHOD__);
                    }
                }
            }
        );

        // Add nutrition info + force recalculate button to recipe entry sidebar
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_SIDEBAR_HTML,
            function(DefineHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                if ($entry->type->handle !== 'recipe') {
                    return;
                }

                $event->html .= Craft::$app->getView()->renderTemplate(
                    'recipe-helper/nutrition/_sidebar',
                    ['entry' => $entry]
                );
            }
        );

        // Register template roots so Craft can find our module templates
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function(RegisterTemplateRootsEvent $event) {
                $event->roots['recipe-helper'] = __DIR__ . '/templates';
            }
        );
    }
}
