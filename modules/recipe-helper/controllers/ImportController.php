<?php

namespace MattBloomfield\RecipeHelper\controllers;

use Craft;
use craft\web\Controller;
use craft\web\UploadedFile;
use MattBloomfield\RecipeHelper\services\ImageParser;
use MattBloomfield\RecipeHelper\services\RecipeImportService;
use MattBloomfield\RecipeHelper\services\UrlParser;
use yii\web\Response;

class ImportController extends Controller
{
    /**
     * Render the import form.
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('recipe-helper/import/index');
    }

    /**
     * Handle URL import submission.
     */
    public function actionImportUrl(): Response
    {
        $this->requirePostRequest();

        $url = Craft::$app->getRequest()->getRequiredBodyParam('url');

        try {
            $parser = new UrlParser();
            $data = $parser->parse($url);

            $service = new RecipeImportService();
            $entry = $service->createRecipe($data);

            Craft::$app->getSession()->setNotice('Recipe imported! Review the draft and add categories before enabling.');

            return $this->redirect($entry->getCpEditUrl());
        } catch (\Throwable $e) {
            Craft::$app->getSession()->setError('Import failed: ' . $e->getMessage());
            return $this->redirect('recipe-import');
        }
    }

    /**
     * Handle image upload import.
     */
    public function actionImportImage(): Response
    {
        $this->requirePostRequest();

        $file = UploadedFile::getInstanceByName('recipeImage');
        if (!$file) {
            Craft::$app->getSession()->setError('No image file uploaded.');
            return $this->redirect('recipe-import');
        }

        try {
            $parser = new ImageParser();
            $data = $parser->parse($file->tempName);

            $service = new RecipeImportService();
            $entry = $service->createRecipe($data);

            Craft::$app->getSession()->setNotice('Recipe imported from image! Review the draft and add categories before enabling.');

            return $this->redirect($entry->getCpEditUrl());
        } catch (\Throwable $e) {
            Craft::$app->getSession()->setError('Import failed: ' . $e->getMessage());
            return $this->redirect('recipe-import');
        }
    }
}
