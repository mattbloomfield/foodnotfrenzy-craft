<?php

namespace MattBloomfield\RecipeHelper\controllers;

use CraftCms\Cms\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use MattBloomfield\RecipeHelper\services\ImageParser;
use MattBloomfield\RecipeHelper\services\RecipeImportService;
use MattBloomfield\RecipeHelper\services\UrlParser;

use function CraftCms\Cms\cp_url;

/**
 * Control panel recipe import (Recipe Import nav item). The form lives at
 * templates/import/index.twig and is served by the `recipe-import` CP route.
 */
class ImportController
{
    /**
     * Handle URL import submission.
     */
    public function importUrl(Request $request): RedirectResponse
    {
        // Like Craft 5's getRequiredBodyParam(): a missing param is a 400.
        abort_unless($request->request->has('url'), 400, 'Request missing required body param');
        $url = $request->request->get('url');

        try {
            $parser = new UrlParser();
            $data = $parser->parse($url);

            $service = new RecipeImportService();
            $entry = $service->createRecipe($data);

            Flash::notice('Recipe imported! Review the draft and add categories before enabling.');

            return redirect($entry->getCpEditUrl());
        } catch (\Throwable $e) {
            Flash::error('Import failed: ' . $e->getMessage());
            return redirect(cp_url('recipe-import'));
        }
    }

    /**
     * Handle image upload import.
     */
    public function importImage(Request $request): RedirectResponse
    {
        $file = $request->file('recipeImage');
        if (!$file || !$file->isValid()) {
            Flash::error('No image file uploaded.');
            return redirect(cp_url('recipe-import'));
        }

        try {
            $parser = new ImageParser();
            $data = $parser->parse($file->getRealPath());

            $service = new RecipeImportService();
            $entry = $service->createRecipe($data);

            Flash::notice('Recipe imported from image! Review the draft and add categories before enabling.');

            return redirect($entry->getCpEditUrl());
        } catch (\Throwable $e) {
            Flash::error('Import failed: ' . $e->getMessage());
            return redirect(cp_url('recipe-import'));
        }
    }
}
