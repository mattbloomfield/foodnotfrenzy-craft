<?php

namespace MattBloomfield\RecipeHelper\controllers;

use Craft;
use craft\web\Controller;
use MattBloomfield\RecipeHelper\services\RecipeApiService;
use yii\web\Response;

/**
 * Public read-only JSON API for querying recipes.
 *
 * Auth: every request must present the shared secret from the RECIPES_API_KEY
 * env var, either as an `X-API-Key` header or an `apiKey` query param.
 *
 * Routes (registered in RecipeHelper::attachEventHandlers):
 *   GET /api/recipes            → actionRecipes  (search/filter)
 *   GET /api/recipes/<slug>     → actionRecipe   (single recipe)
 *   GET /api/categories         → actionCategories
 *   GET /api/openapi.json       → actionOpenapi  (spec; no auth)
 */
class ApiController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public $enableCsrfValidation = false;

    public function beforeAction($action): bool
    {
        // Always answer CORS preflight and let the spec be fetched without a key.
        $this->response->getHeaders()
            ->set('Access-Control-Allow-Origin', '*')
            ->set('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->set('Access-Control-Allow-Headers', 'X-API-Key, Content-Type');

        if (Craft::$app->getRequest()->getIsOptions()) {
            Craft::$app->getResponse()->format = Response::FORMAT_RAW;
            Craft::$app->end();
        }

        if (!parent::beforeAction($action)) {
            return false;
        }

        // The OpenAPI spec is public so agents can discover the API before auth.
        if ($action->id === 'openapi') {
            return true;
        }

        $this->requireApiKey();

        return true;
    }

    /**
     * GET /api/recipes — search & filter.
     */
    public function actionRecipes(): Response
    {
        $params = Craft::$app->getRequest()->getQueryParams();
        return $this->asJson($this->service()->search($params));
    }

    /**
     * GET /api/recipes/<slug> — full recipe detail.
     */
    public function actionRecipe(string $slug): Response
    {
        $recipe = $this->service()->getRecipeBySlug($slug);

        if ($recipe === null) {
            $this->response->setStatusCode(404);
            return $this->asJson(['error' => 'Recipe not found', 'slug' => $slug]);
        }

        return $this->asJson($recipe);
    }

    /**
     * GET /api/categories — valid values for the `category` filter.
     */
    public function actionCategories(): Response
    {
        return $this->asJson(['results' => $this->service()->getCategories()]);
    }

    /**
     * GET /api/openapi.json — machine-readable spec for agents/tool-calling.
     */
    public function actionOpenapi(): Response
    {
        return $this->asJson($this->openApiSpec());
    }

    // --- Internals ----------------------------------------------------------

    private function service(): RecipeApiService
    {
        return new RecipeApiService();
    }

    private function requireApiKey(): void
    {
        $expected = Craft::parseEnv('$RECIPES_API_KEY');

        if (empty($expected)) {
            $this->response->setStatusCode(503);
            Craft::$app->getResponse()->data = ['error' => 'API not configured (RECIPES_API_KEY unset)'];
            Craft::$app->getResponse()->format = Response::FORMAT_JSON;
            Craft::$app->end();
        }

        $request = Craft::$app->getRequest();
        $provided = $request->getHeaders()->get('X-API-Key')
            ?: $request->getQueryParam('apiKey', '');

        if (!is_string($provided) || !hash_equals((string)$expected, $provided)) {
            $this->response->setStatusCode(401);
            Craft::$app->getResponse()->data = ['error' => 'Invalid or missing API key'];
            Craft::$app->getResponse()->format = Response::FORMAT_JSON;
            Craft::$app->end();
        }
    }

    private function openApiSpec(): array
    {
        $base = rtrim(Craft::$app->getSites()->getPrimarySite()->getBaseUrl() ?? '/', '/');

        $nutrientParams = [];
        foreach (['calories', 'protein', 'carbs', 'fat', 'saturatedFat', 'fiber', 'sugar', 'sodium'] as $n) {
            foreach (['min', 'max'] as $bound) {
                $nutrientParams[] = [
                    'name'        => $bound . ucfirst($n),
                    'in'          => 'query',
                    'description' => ucfirst($bound) . " {$n} per serving.",
                    'required'    => false,
                    'schema'      => ['type' => 'number'],
                ];
            }
        }

        $searchParams = array_merge([
            ['name' => 'q', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'],
                'description' => 'Full-text search across recipe title and description.'],
            ['name' => 'ingredient', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'],
                'description' => 'Only recipes containing an ingredient whose name matches this substring.'],
            ['name' => 'category', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'],
                'description' => 'Category slug (see GET /api/categories).'],
            ['name' => 'difficulty', 'in' => 'query', 'required' => false,
                'schema' => ['type' => 'string', 'enum' => ['child', 'beginner', 'average', 'difficult']],
                'description' => 'Recipe difficulty level.'],
            ['name' => 'maxPrepTime', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'],
                'description' => 'Maximum prep time in minutes.'],
            ['name' => 'maxCookTime', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'],
                'description' => 'Maximum cook time in minutes.'],
            ['name' => 'maxTotalTime', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer'],
                'description' => 'Maximum total (prep + cook) time in minutes.'],
        ], $nutrientParams, [
            ['name' => 'sort', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'],
                'description' => 'Sort as "field:dir", e.g. "protein:desc". Fields: title, protein, calories, carbs, fat, fiber, sugar, sodium, prepTime, cookTime, rating, date. Default: date:desc.'],
            ['name' => 'limit', 'in' => 'query', 'required' => false,
                'schema' => ['type' => 'integer', 'default' => 20, 'maximum' => 100],
                'description' => 'Results per page (max 100).'],
            ['name' => 'offset', 'in' => 'query', 'required' => false,
                'schema' => ['type' => 'integer', 'default' => 0], 'description' => 'Pagination offset.'],
        ]);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title'       => 'Food Not Frenzy Recipe API',
                'version'     => '1.0.0',
                'description' => 'Read-only API for searching recipes by name, ingredient, and per-serving nutrition. '
                    . 'Example: recipes with >20g protein and <500 calories per serving → '
                    . 'GET /api/recipes?minProtein=20&maxCalories=500&sort=protein:desc. '
                    . 'Authenticate with the X-API-Key header (or apiKey query param).',
            ],
            'servers' => [['url' => $base]],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyHeader' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key'],
                ],
            ],
            'security' => [['ApiKeyHeader' => []]],
            'paths' => [
                '/api/recipes' => [
                    'get' => [
                        'operationId' => 'searchRecipes',
                        'summary'     => 'Search and filter recipes by name, ingredient, and nutrition.',
                        'parameters'  => $searchParams,
                        'responses'   => ['200' => ['description' => 'Matching recipes with total count.']],
                    ],
                ],
                '/api/recipes/{slug}' => [
                    'get' => [
                        'operationId' => 'getRecipe',
                        'summary'     => 'Get a single recipe with full ingredients, instructions, and nutrition.',
                        'parameters'  => [[
                            'name' => 'slug', 'in' => 'path', 'required' => true,
                            'schema' => ['type' => 'string'],
                        ]],
                        'responses'   => [
                            '200' => ['description' => 'The recipe.'],
                            '404' => ['description' => 'Not found.'],
                        ],
                    ],
                ],
                '/api/categories' => [
                    'get' => [
                        'operationId' => 'listCategories',
                        'summary'     => 'List recipe categories (valid values for the category filter).',
                        'responses'   => ['200' => ['description' => 'Categories.']],
                    ],
                ],
            ],
        ];
    }
}
