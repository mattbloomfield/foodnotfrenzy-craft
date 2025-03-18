<?php

namespace MattBloomfield\RecipeHelper;

use Craft;
use MattBloomfield\RecipeHelper\twig\RecipeFractionConverter;
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
        // Register event handlers here ...
        // (see https://craftcms.com/docs/5.x/extend/events.html to get started)
    }
}
