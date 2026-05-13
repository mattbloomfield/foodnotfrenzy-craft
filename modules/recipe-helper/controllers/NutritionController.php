<?php

namespace MattBloomfield\RecipeHelper\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use MattBloomfield\RecipeHelper\services\NutritionService;
use yii\web\Response;

class NutritionController extends Controller
{
    public function actionCalculate(): Response
    {
        $this->requirePostRequest();

        $entryId = Craft::$app->getRequest()->getRequiredBodyParam('entryId');
        $entry = Entry::find()->id($entryId)->status(null)->one();

        if (!$entry) {
            Craft::$app->getSession()->setError('Recipe not found.');
            return $this->redirect(Craft::$app->getRequest()->getReferrer());
        }

        $service = new NutritionService();
        $result = $service->calculateAndSave($entry);

        if ($result['success']) {
            Craft::$app->getSession()->setNotice($result['message']);
        } else {
            Craft::$app->getSession()->setError($result['message']);
        }

        return $this->redirect($entry->getCpEditUrl());
    }
}
