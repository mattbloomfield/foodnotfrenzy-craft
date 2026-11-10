<?php

namespace MattBloomfield\RecipeHelper\controllers;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Http\Controllers\SiteRouteController;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fallback for old category URLs after the taxonomy was re-faceted (parents
 * changed, so URIs moved, e.g. /categories/desserts → /categories/course/desserts).
 *
 * Registered as the `categories/{catUri}` site route. Craft routes element URIs
 * before custom routes, so a URI that belongs to an element (a real category
 * page) is handed straight back to Craft’s element routing. Otherwise we look
 * the category up by its final slug segment and 301 to its real URL.
 */
class CategoryRedirectController
{
    /**
     * Old category slugs that were renamed/merged during the taxonomy migration,
     * mapped to the slug that replaced them.
     */
    private const SLUG_ALIASES = [
        'crockpot'      => 'slow-cooker',
        'sides'         => 'side-dish',
        'meatless'      => 'vegetarian',
        'breads'        => 'bread',
        'sandwiches-2'  => 'sandwiches',
        'dessert-rolls' => 'sweet-rolls',
        'cultural'      => 'cuisine',
        'holidays'      => 'occasion',
        'snack'         => 'appetizers-snacks',
        'lunch'         => 'main-dish',
        'tarts'         => 'desserts',
        'donuts'        => 'desserts',
    ];

    public function __invoke(Request $request, string $catUri): Response
    {
        $requestPath = Sites::getRequestPath($request);

        if (Elements::getElementByUri($requestPath, Sites::getCurrentSite()->id, true)) {
            $request->route()?->forgetParameter('catUri');

            return app(SiteRouteController::class)($request);
        }

        $segments = array_filter(explode('/', trim($catUri, '/')));
        $slug = end($segments);
        $slug = self::SLUG_ALIASES[$slug] ?? $slug;

        $category = $slug ? Entry::find()
            ->section('categories')
            ->slug($slug)
            ->status('enabled')
            ->one() : null;

        if ($category) {
            $target = $category->getUrl();
            $targetPath = trim((string)parse_url($target, PHP_URL_PATH), '/');

            // Guard against redirecting a URL to itself (would loop).
            if ($target && trim($requestPath, '/') !== $targetPath) {
                // An empty 301, as Craft 5 sent (no HTML meta-refresh body).
                return new Response('', 301, [
                    'Location' => $target,
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]);
            }
        }

        abort(404, 'Category not found');
    }
}
