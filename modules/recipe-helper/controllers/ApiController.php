<?php

namespace MattBloomfield\RecipeHelper\controllers;

use CraftCms\Cms\Support\Env;
use CraftCms\Cms\Support\Facades\Sites;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MattBloomfield\RecipeHelper\services\RecipeApiService;

/**
 * Public read-only JSON API for querying recipes.
 *
 * Auth: every request must present the shared secret from the RECIPES_API_KEY
 * env var, either as an `X-API-Key` header or an `apiKey` query param.
 *
 * Routes (registered in RecipeHelperServiceProvider::registerRoutes):
 *   GET /api/recipes            → recipes     (search/filter)
 *   GET /api/recipes/<slug>     → recipe      (single recipe)
 *   GET /api/categories         → categories
 *   GET /api/openapi.json       → openapi     (spec; no auth)
 *   OPTIONS /api/*              → preflight
 */
class ApiController
{
    private const CORS_HEADERS = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Methods' => 'GET, OPTIONS',
        'Access-Control-Allow-Headers' => 'X-API-Key, Content-Type',
    ];

    /**
     * GET /api/recipes — search & filter.
     */
    public function recipes(Request $request): JsonResponse
    {
        return $this->requireApiKey($request)
            ?? $this->json($this->service()->search($request->query()));
    }

    /**
     * GET /api/recipes/<slug> — full recipe detail.
     */
    public function recipe(Request $request, string $slug): JsonResponse
    {
        if ($denied = $this->requireApiKey($request)) {
            return $denied;
        }

        $recipe = $this->service()->getRecipeBySlug($slug);

        if ($recipe === null) {
            return $this->json(['error' => 'Recipe not found', 'slug' => $slug], 404);
        }

        return $this->json($recipe);
    }

    /**
     * GET /api/categories — valid values for the `category` filter.
     */
    public function categories(Request $request): JsonResponse
    {
        return $this->requireApiKey($request)
            ?? $this->json(['results' => $this->service()->getCategories()]);
    }

    /**
     * GET /api/openapi.json — machine-readable spec for agents/tool-calling.
     * Public, so agents can discover the API before auth.
     */
    public function openapi(): JsonResponse
    {
        return $this->json($this->openApiSpec());
    }

    /**
     * OPTIONS /api/* — CORS preflight. (Preflights that carry an Origin are
     * answered earlier by Laravel's CORS middleware; see config/cors.php.)
     */
    public function preflight(): Response
    {
        return new Response('', 200, self::CORS_HEADERS);
    }

    // --- Internals ----------------------------------------------------------

    private function service(): RecipeApiService
    {
        return new RecipeApiService();
    }

    /**
     * JSON encoded the way the Craft 5 API encoded it (unescaped slashes and unicode).
     */
    private function json(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse($data, $status, [
            ...self::CORS_HEADERS,
            'Content-Type' => 'application/json; charset=UTF-8',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Returns an error response if the request doesn’t carry the API key.
     */
    private function requireApiKey(Request $request): ?JsonResponse
    {
        $expected = Env::parse('$RECIPES_API_KEY');

        if (empty($expected)) {
            return $this->json(['error' => 'API not configured (RECIPES_API_KEY unset)'], 503);
        }

        $provided = $request->headers->get('X-API-Key')
            ?: $request->query('apiKey', '');

        if (!is_string($provided) || !hash_equals((string)$expected, $provided)) {
            return $this->json(['error' => 'Invalid or missing API key'], 401);
        }

        return null;
    }

    private function openApiSpec(): array
    {
        $base = rtrim(Sites::getPrimarySite()->getBaseUrl() ?? '/', '/');

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
