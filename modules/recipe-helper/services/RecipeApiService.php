<?php

namespace MattBloomfield\RecipeHelper\services;

use Craft;
use craft\elements\Entry;

/**
 * Read-only recipe query + serialization service backing the public JSON API.
 *
 * DB-level filters (nutrition ranges, prep/cook time, difficulty, category,
 * full-text) are pushed into the element query. Filters that can't be expressed
 * cheaply as query params (ingredient-name match, combined total time) are
 * applied in PHP after the query runs.
 */
class RecipeApiService
{
    private const SECTION = 'recipes';
    private const CATEGORY_SECTION = 'categories';

    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    /**
     * Map the public nutrient names used in query params to recipe field handles.
     * All nutrition fields are stored per-serving.
     */
    private const NUTRIENT_FIELDS = [
        'calories'     => 'nutritionCalories',
        'protein'      => 'nutritionProtein',
        'carbs'        => 'nutritionCarbs',
        'fat'          => 'nutritionFat',
        'saturatedFat' => 'nutritionSaturatedFat',
        'fiber'        => 'nutritionFiber',
        'sugar'        => 'nutritionSugar',
        'sodium'       => 'nutritionSodium',
    ];

    /**
     * Fields that `sort` may reference, mapped to their query column/handle.
     */
    private const SORTABLE = [
        'title'      => 'title',
        'protein'    => 'nutritionProtein',
        'calories'   => 'nutritionCalories',
        'carbs'      => 'nutritionCarbs',
        'fat'        => 'nutritionFat',
        'fiber'      => 'nutritionFiber',
        'sugar'      => 'nutritionSugar',
        'sodium'     => 'nutritionSodium',
        'prepTime'   => 'prepTime',
        'cookTime'   => 'cookTime',
        'rating'     => 'rating',
        'date'       => 'postDate',
    ];

    /**
     * Search recipes.
     *
     * @param array $params Raw request query params.
     * @return array{total:int, limit:int, offset:int, results:array}
     */
    public function search(array $params): array
    {
        $limit  = $this->clampInt($params['limit'] ?? null, self::DEFAULT_LIMIT, 1, self::MAX_LIMIT);
        $offset = max(0, (int)($params['offset'] ?? 0));

        $query = Entry::find()
            ->section(self::SECTION)
            ->status('enabled');

        // --- Nutrition range filters (DB level) ---
        foreach (self::NUTRIENT_FIELDS as $key => $handle) {
            $min = $this->numOrNull($params['min' . ucfirst($key)] ?? null);
            $max = $this->numOrNull($params['max' . ucfirst($key)] ?? null);
            $condition = $this->rangeCondition($min, $max);
            if ($condition !== null) {
                $query->{$handle}($condition);
            }
        }

        // --- Time filters (DB level, minutes) ---
        if (($maxPrep = $this->numOrNull($params['maxPrepTime'] ?? null)) !== null) {
            $query->prepTime("<= $maxPrep");
        }
        if (($maxCook = $this->numOrNull($params['maxCookTime'] ?? null)) !== null) {
            $query->cookTime("<= $maxCook");
        }

        // --- Difficulty (Dropdown value) ---
        if (!empty($params['difficulty'])) {
            $query->difficulty((string)$params['difficulty']);
        }

        // --- Category (by slug) ---
        if (!empty($params['category'])) {
            $category = Entry::find()
                ->section(self::CATEGORY_SECTION)
                ->slug((string)$params['category'])
                ->status('enabled')
                ->one();

            if (!$category) {
                // Unknown category → no matches, but still a valid response.
                return ['total' => 0, 'limit' => $limit, 'offset' => $offset, 'results' => []];
            }
            $query->relatedTo(['targetElement' => $category, 'field' => 'categories']);
        }

        // --- Full-text search (title/description/etc.) ---
        if (!empty($params['q'])) {
            $query->search((string)$params['q']);
        }

        $query->orderBy($this->orderBy($params['sort'] ?? null));

        // Post-query filters that can't be expressed as query params.
        $ingredient  = isset($params['ingredient']) ? trim((string)$params['ingredient']) : '';
        $maxTotal    = $this->numOrNull($params['maxTotalTime'] ?? null);
        $needsPost   = $ingredient !== '' || $maxTotal !== null;

        if (!$needsPost) {
            $total   = (int)$query->count();
            $entries = $query->offset($offset)->limit($limit)->all();
        } else {
            $all = $query->all();
            $all = array_values(array_filter($all, function(Entry $entry) use ($ingredient, $maxTotal) {
                if ($maxTotal !== null) {
                    $total = (int)($entry->prepTime ?? 0) + (int)($entry->cookTime ?? 0);
                    if ($total > $maxTotal) {
                        return false;
                    }
                }
                if ($ingredient !== '' && !$this->recipeHasIngredient($entry, $ingredient)) {
                    return false;
                }
                return true;
            }));
            $total   = count($all);
            $entries = array_slice($all, $offset, $limit);
        }

        return [
            'total'   => $total,
            'limit'   => $limit,
            'offset'  => $offset,
            'results' => array_map(fn(Entry $e) => $this->serializeSummary($e), $entries),
        ];
    }

    /**
     * Fetch a single recipe by slug, fully serialized. Null if not found.
     */
    public function getRecipeBySlug(string $slug): ?array
    {
        $entry = Entry::find()
            ->section(self::SECTION)
            ->slug($slug)
            ->status('enabled')
            ->one();

        return $entry ? $this->serializeDetail($entry) : null;
    }

    /**
     * List all recipe categories (valid values for the `category` filter).
     */
    public function getCategories(): array
    {
        $categories = Entry::find()
            ->section(self::CATEGORY_SECTION)
            ->status('enabled')
            ->orderBy('title asc')
            ->all();

        return array_map(fn(Entry $c) => [
            'title' => $c->title,
            'slug'  => $c->slug,
        ], $categories);
    }

    // --- Serialization ------------------------------------------------------

    private function serializeSummary(Entry $entry): array
    {
        return [
            'title'       => $entry->title,
            'slug'        => $entry->slug,
            'url'         => $entry->getUrl(),
            'description' => $entry->description ?: null,
            'image'       => $this->imageUrl($entry),
            'servings'    => $this->floatOrNull($entry->servings),
            'prepTime'    => $this->intOrNull($entry->prepTime),
            'cookTime'    => $this->intOrNull($entry->cookTime),
            'totalTime'   => $this->totalTime($entry),
            'difficulty'  => $this->difficultyValue($entry),
            'categories'  => $this->categorySlugs($entry),
            'nutrition'   => $this->nutrition($entry),
        ];
    }

    private function serializeDetail(Entry $entry): array
    {
        $summary = $this->serializeSummary($entry);

        return array_merge($summary, [
            'ingredients'  => $this->ingredients($entry),
            'instructions' => $this->instructions($entry),
            'notes'        => $entry->notes ?: null,
        ]);
    }

    /**
     * Nutrition facts, per serving. Null values omitted-as-null so consumers
     * can distinguish "not calculated" from zero.
     */
    private function nutrition(Entry $entry): array
    {
        return [
            'calories'     => $this->floatOrNull($entry->nutritionCalories),
            'protein'      => $this->floatOrNull($entry->nutritionProtein),
            'carbs'        => $this->floatOrNull($entry->nutritionCarbs),
            'fat'          => $this->floatOrNull($entry->nutritionFat),
            'saturatedFat' => $this->floatOrNull($entry->nutritionSaturatedFat),
            'fiber'        => $this->floatOrNull($entry->nutritionFiber),
            'sugar'        => $this->floatOrNull($entry->nutritionSugar),
            'sodium'       => $this->floatOrNull($entry->nutritionSodium),
            'unit'         => 'per serving',
        ];
    }

    private function ingredients(Entry $entry): array
    {
        $out = [];

        foreach ($entry->getFieldValue('ingredientsAndInstructions')->all() as $block) {
            if ($block->type->handle !== 'ingredientsBlock') {
                continue;
            }
            $rows = $block->getFieldValue('ingredientsList');
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $name = trim($row['ingredientName'] ?? '');
                if ($name === '') {
                    continue;
                }
                $out[] = [
                    'quantity'   => ($q = trim($row['quantity'] ?? '')) !== '' ? $q : null,
                    'unit'       => ($u = ($row['unit'] ?? 'none')) !== 'none' ? $u : null,
                    'ingredient' => $name,
                    'notes'      => ($n = trim($row['notes'] ?? '')) !== '' ? $n : null,
                    'optional'   => !empty($row['optional']),
                ];
            }
        }

        return $out;
    }

    private function instructions(Entry $entry): array
    {
        $out = [];

        foreach ($entry->getFieldValue('ingredientsAndInstructions')->all() as $block) {
            if ($block->type->handle !== 'instructionsBlock') {
                continue;
            }
            $rows = $block->getFieldValue('instructions');
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                $step = trim($row['instruction'] ?? '');
                if ($step !== '') {
                    $out[] = $step;
                }
            }
        }

        return $out;
    }

    // --- Helpers ------------------------------------------------------------

    private function recipeHasIngredient(Entry $entry, string $needle): bool
    {
        $needle = mb_strtolower($needle);
        foreach ($this->ingredients($entry) as $ing) {
            if (str_contains(mb_strtolower($ing['ingredient']), $needle)) {
                return true;
            }
        }
        return false;
    }

    private function imageUrl(Entry $entry): ?string
    {
        $asset = $entry->image->one();
        return $asset ? $asset->getUrl() : null;
    }

    private function categorySlugs(Entry $entry): array
    {
        return array_map(fn($c) => [
            'title' => $c->title,
            'slug'  => $c->slug,
        ], $entry->categories->all());
    }

    private function difficultyValue(Entry $entry): ?string
    {
        $value = $entry->difficulty;
        if ($value instanceof \craft\fields\data\SingleOptionFieldData) {
            return $value->value ?: null;
        }
        return $value ? (string)$value : null;
    }

    private function totalTime(Entry $entry): ?int
    {
        $prep = $this->intOrNull($entry->prepTime);
        $cook = $this->intOrNull($entry->cookTime);
        if ($prep === null && $cook === null) {
            return null;
        }
        return (int)$prep + (int)$cook;
    }

    /**
     * Build a Craft number-field query condition from optional min/max bounds.
     */
    private function rangeCondition(?float $min, ?float $max): array|string|null
    {
        if ($min !== null && $max !== null) {
            return ['and', ">= $min", "<= $max"];
        }
        if ($min !== null) {
            return ">= $min";
        }
        if ($max !== null) {
            return "<= $max";
        }
        return null;
    }

    private function orderBy(?string $sort): array
    {
        $default = ['postDate' => SORT_DESC];
        if (!$sort) {
            return $default;
        }

        [$field, $dir] = array_pad(explode(':', $sort, 2), 2, 'asc');
        $field = trim($field);
        $dir   = strtolower(trim($dir)) === 'desc' ? SORT_DESC : SORT_ASC;

        if (!isset(self::SORTABLE[$field])) {
            return $default;
        }

        return [self::SORTABLE[$field] => $dir];
    }

    private function clampInt($value, int $default, int $min, int $max): int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return $default;
        }
        return max($min, min($max, (int)$value));
    }

    private function numOrNull($value): ?float
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        return (float)$value;
    }

    private function intOrNull($value): ?int
    {
        return ($value === null || $value === '') ? null : (int)$value;
    }

    private function floatOrNull($value): ?float
    {
        return ($value === null || $value === '') ? null : (float)$value;
    }
}
