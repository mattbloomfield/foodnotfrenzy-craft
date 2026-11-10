<?php

use Illuminate\Support\Facades\Route;

/*
 * Site routes. They run through Craft’s site middleware (`craft`, `craft.web`)
 * like Craft’s own routes do; the lazy session keeps responses cookie-free.
 *
 * The recipe API and old-category redirects are registered by the Recipe
 * Helper module (modules/recipe-helper/RecipeHelperServiceProvider.php).
 */

Route::middleware(['craft', 'craft.web'])->withLazySession()->group(function () {
    // Replaces SEOmate's sitemap (see resources/views/_sitemap.twig).
    Route::view('sitemap.xml', '_sitemap');
});
