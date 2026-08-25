<?php

namespace MattBloomfield\RecipeHelper\controllers;

use Craft;
use craft\elements\Entry;
use craft\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Fallback for old category URLs after the taxonomy was re-faceted (parents
 * changed, so URIs moved, e.g. /categories/desserts → /categories/course/desserts).
 *
 * Only reached when no category element matches the requested URI — Craft
 * resolves element URIs before custom URL rules — so valid pages never hit this.
 * We look the category up by its final slug segment and 301 to its real URL.
 */
class CategoryRedirectController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

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

    public function actionRedirect(string $catUri): Response
    {
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
            $requestPath = trim(Craft::$app->getRequest()->getPathInfo(), '/');
            $targetPath = trim((string)parse_url($target, PHP_URL_PATH), '/');

            // Guard against redirecting a URL to itself (would loop).
            if ($target && $requestPath !== $targetPath) {
                return $this->redirect($target, 301);
            }
        }

        throw new NotFoundHttpException('Category not found');
    }
}
