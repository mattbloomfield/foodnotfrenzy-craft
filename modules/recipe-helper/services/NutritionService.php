<?php

namespace MattBloomfield\RecipeHelper\services;

use Craft;
use craft\elements\Entry;

class NutritionService
{
    private const API_URL = 'https://api.spoonacular.com/recipes/analyze';

    /**
     * Map unit codes (stored in the ingredientsList table field) to
     * natural-language words that the Spoonacular API understands.
     */
    private const UNIT_DISPLAY = [
        'none'    => '',
        'box'     => 'box',
        'can'     => 'can',
        'clove'   => 'clove',
        'C'       => 'cup',
        'ds'      => 'dash',
        'dr'      => 'drop',
        'fl oz'   => 'fl oz',
        'gal'     => 'gallon',
        'inch'    => 'inch',
        'large'   => 'large',
        'medium'  => 'medium',
        'oz'      => 'oz',
        'package' => 'package',
        'pn'      => 'pinch',
        'pt'      => 'pint',
        'lbs'     => 'pound',
        'qt'      => 'quart',
        'slice'   => 'slice',
        'smdg'    => 'smidgen',
        'stalk'   => 'stalk',
        'stick'   => 'stick',
        'tsp'     => 'teaspoon',
        'Tbsp'    => 'tablespoon',
    ];

    /**
     * Map Spoonacular nutrient names to our field handles.
     */
    private const NUTRIENT_MAP = [
        'Calories'       => ['field' => 'nutritionCalories', 'decimals' => 0],
        'Fat'            => ['field' => 'nutritionFat', 'decimals' => 1],
        'Saturated Fat'  => ['field' => 'nutritionSaturatedFat', 'decimals' => 1],
        'Carbohydrates'  => ['field' => 'nutritionCarbs', 'decimals' => 1],
        'Fiber'          => ['field' => 'nutritionFiber', 'decimals' => 1],
        'Sugar'          => ['field' => 'nutritionSugar', 'decimals' => 1],
        'Protein'        => ['field' => 'nutritionProtein', 'decimals' => 1],
        'Sodium'         => ['field' => 'nutritionSodium', 'decimals' => 0],
    ];

    /**
     * Build a hash of the current ingredients + servings so we can detect changes.
     */
    public function getIngredientHash(Entry $entry): string
    {
        $ingredientStrings = $this->buildIngredientStrings($entry);
        $servings = max(1, (int)($entry->getFieldValue('servings') ?: 1));
        return md5(json_encode(['servings' => $servings, 'ingredients' => $ingredientStrings]));
    }

    /**
     * Check whether the stored nutrition data is still current.
     */
    public function needsRecalculation(Entry $entry): bool
    {
        $rawJson = $entry->getFieldValue('nutritionRawJson');
        if (empty($rawJson)) {
            return true;
        }

        $stored = json_decode($rawJson, true);
        $storedHash = $stored['ingredientHash'] ?? null;
        if (!$storedHash) {
            // Legacy data without hash — assume stale
            return true;
        }

        return $storedHash !== $this->getIngredientHash($entry);
    }

    /**
     * Calculate nutrition via Spoonacular and save to the entry.
     *
     * @param bool $force  Skip the ingredient-hash check and always call the API.
     * @return array{success: bool, message: string}
     */
    public function calculateAndSave(Entry $entry, bool $force = false): array
    {
        $skippedApi = false;

        if (!$force && !$this->needsRecalculation($entry)) {
            $skippedApi = true;
        }

        if (!$skippedApi) {
            $apiKey = Craft::parseEnv('$SPOONACULAR_API_KEY');

            if (empty($apiKey)) {
                return ['success' => false, 'message' => 'SPOONACULAR_API_KEY not configured in .env'];
            }

            $ingredientStrings = $this->buildIngredientStrings($entry);

            if (empty($ingredientStrings)) {
                return ['success' => false, 'message' => 'No ingredients found on this recipe'];
            }

            $servings = max(1, (int)($entry->getFieldValue('servings') ?: 1));

            $payload = [
                'title'        => $entry->title,
                'servings'     => $servings,
                'ingredients'  => $ingredientStrings,
            ];

            $url = self::API_URL . '?' . http_build_query([
                'apiKey'           => $apiKey,
                'includeNutrition' => 'true',
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return ['success' => false, 'message' => "cURL error: $error"];
            }

            if ($httpCode !== 200) {
                $body = json_decode($response, true);
                $msg = $body['message'] ?? $body['error'] ?? "HTTP $httpCode";
                return ['success' => false, 'message' => "Spoonacular API error: $msg"];
            }

            $data = json_decode($response, true);

            // Spoonacular returns nutrition.nutrients as an array of
            // { name, amount, unit, percentOfDailyNeeds }
            $nutrients = $data['nutrition']['nutrients'] ?? null;

            if (!$nutrients) {
                return ['success' => false, 'message' => 'Unexpected API response — no nutrition data found'];
            }

            // Index nutrients by name for easy lookup
            $nutrientsByName = [];
            foreach ($nutrients as $n) {
                $nutrientsByName[$n['name']] = $n;
            }

            // Spoonacular already returns per-serving values when you provide servings,
            // so no need to divide
            foreach (self::NUTRIENT_MAP as $apiName => $config) {
                $value = isset($nutrientsByName[$apiName])
                    ? round((float)$nutrientsByName[$apiName]['amount'], $config['decimals'])
                    : null;
                $entry->setFieldValue($config['field'], $value);
            }

            // Store the API response along with the ingredient hash for change detection
            $entry->setFieldValue('nutritionRawJson', json_encode([
                'ingredientHash' => $this->getIngredientHash($entry),
                'apiResponse'    => $data,
            ], JSON_PRETTY_PRINT));
        }

        if (!Craft::$app->elements->saveElement($entry)) {
            $errors = implode(', ', $entry->getFirstErrors());
            return ['success' => false, 'message' => "Failed to save entry: $errors"];
        }

        $message = $skippedApi
            ? 'Entry saved — nutrition unchanged (ingredients not modified)'
            : 'Nutrition data calculated and saved';

        return ['success' => true, 'message' => $message];
    }

    /**
     * Build an array of natural-language ingredient strings from all
     * ingredientsBlock Matrix blocks on the entry.
     */
    public function buildIngredientStrings(Entry $entry): array
    {
        $strings = [];

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
                if (empty($name)) {
                    continue;
                }

                $quantity = $this->fractionToDecimal(trim($row['quantity'] ?? ''));
                $unitCode = $row['unit'] ?? 'none';
                $unitWord = self::UNIT_DISPLAY[$unitCode] ?? $unitCode;

                $parts = [];
                if ($quantity > 0) {
                    $parts[] = $quantity;
                }
                if (!empty($unitWord)) {
                    $parts[] = $unitWord;
                }
                $parts[] = $name;

                $strings[] = implode(' ', $parts);
            }
        }

        return $strings;
    }

    /**
     * Convert fraction strings like "1/2", "1 1/2" to float.
     */
    public function fractionToDecimal(string $value): float
    {
        $value = trim($value);

        if ($value === '' || $value === '0') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return (float)$value;
        }

        // Mixed number: "1 1/2"
        if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $value, $m)) {
            return (float)$m[1] + ((float)$m[2] / (float)$m[3]);
        }

        // Simple fraction: "1/2"
        if (preg_match('/^(\d+)\/(\d+)$/', $value, $m)) {
            return (float)$m[1] / (float)$m[2];
        }

        return 0.0;
    }
}
