<?php

namespace MattBloomfield\RecipeHelper\services;

use Craft;
use craft\elements\Entry;

/**
 * Classifies a recipe against the faceted category taxonomy using an LLM.
 *
 * Sends the recipe (title, description, ingredients, current tags) plus the
 * controlled vocabulary to the model and constrains the output to an array of
 * category slugs via a JSON-schema enum — so the model can never invent a slug.
 *
 * Provider is chosen by model name: "gemini-*" → Google Gemini
 * (GEMINI_API_KEY), anything else → Anthropic Claude (ANTHROPIC_API_KEY).
 */
class CategorizationService
{
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const CATEGORY_SECTION = 'categories';

    /** @var array{prompt:string, slugs:string[]}|null */
    private ?array $taxonomy = null;

    private NutritionService $nutrition;

    public function __construct()
    {
        $this->nutrition = new NutritionService();
    }

    /**
     * Suggest taxonomy category slugs for a recipe.
     *
     * @return array{success:bool, message:string, slugs:string[]}
     */
    public function categorize(Entry $entry, string $model = 'gemini-flash-lite-latest'): array
    {
        $taxonomy = $this->getTaxonomy();
        if (empty($taxonomy['slugs'])) {
            return ['success' => false, 'message' => 'No taggable categories found in taxonomy', 'slugs' => []];
        }

        $system = $this->systemPrompt($taxonomy['prompt']);
        $userText = $this->recipePrompt($entry);

        return str_starts_with($model, 'gemini')
            ? $this->viaGemini($model, $system, $userText, $taxonomy['slugs'])
            : $this->viaAnthropic($model, $system, $userText, $taxonomy['slugs']);
    }

    // --- Providers ----------------------------------------------------------

    /**
     * @param string[] $allowed
     * @return array{success:bool, message:string, slugs:string[]}
     */
    private function viaGemini(string $model, string $system, string $userText, array $allowed): array
    {
        $apiKey = Craft::parseEnv('$GEMINI_API_KEY');
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'GEMINI_API_KEY not configured in .env', 'slugs' => []];
        }

        $payload = [
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $userText]]]],
            'generationConfig' => [
                'temperature' => 0,
                'responseMimeType' => 'application/json',
                'responseSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'categories' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'enum' => $allowed],
                        ],
                    ],
                    'required' => ['categories'],
                ],
            ],
        ];

        $url = self::GEMINI_URL . rawurlencode($model) . ':generateContent';
        $result = $this->post($url, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ], $payload);

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'], 'slugs' => []];
        }

        $data = $result['data'];
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text === null) {
            $reason = $data['candidates'][0]['finishReason'] ?? 'no content';
            return ['success' => false, 'message' => "Gemini returned no content ($reason)", 'slugs' => []];
        }

        $decoded = json_decode($text, true);
        $slugs = $decoded['categories'] ?? [];

        return ['success' => true, 'message' => 'ok', 'slugs' => $this->filterSlugs($slugs, $allowed)];
    }

    /**
     * @param string[] $allowed
     * @return array{success:bool, message:string, slugs:string[]}
     */
    private function viaAnthropic(string $model, string $system, string $userText, array $allowed): array
    {
        $apiKey = Craft::parseEnv('$ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'ANTHROPIC_API_KEY not configured in .env', 'slugs' => []];
        }

        $payload = [
            'model' => $model,
            'max_tokens' => 1024,
            'system' => $system,
            'tools' => [[
                'name' => 'assign_categories',
                'description' => 'Record every taxonomy category that applies to this recipe.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'categories' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'enum' => $allowed],
                            'description' => 'Slugs of all categories that genuinely apply.',
                        ],
                    ],
                    'required' => ['categories'],
                    'additionalProperties' => false,
                ],
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => 'assign_categories'],
            'messages' => [['role' => 'user', 'content' => $userText]],
        ];

        $result = $this->post(self::ANTHROPIC_URL, [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: ' . self::ANTHROPIC_VERSION,
        ], $payload);

        if (!$result['success']) {
            return ['success' => false, 'message' => $result['message'], 'slugs' => []];
        }

        $data = $result['data'];
        if (($data['stop_reason'] ?? null) === 'refusal') {
            return ['success' => false, 'message' => 'Model refused to respond', 'slugs' => []];
        }

        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'tool_use' && ($block['name'] ?? null) === 'assign_categories') {
                return ['success' => true, 'message' => 'ok',
                    'slugs' => $this->filterSlugs($block['input']['categories'] ?? [], $allowed)];
            }
        }

        return ['success' => false, 'message' => 'No tool call in response', 'slugs' => []];
    }

    // --- Prompt building ----------------------------------------------------

    private function systemPrompt(string $taxonomyText): string
    {
        return <<<PROMPT
You are a meticulous culinary taxonomist tagging recipes for a home-cooking website.

Given a recipe, assign every category from the controlled taxonomy below that
genuinely applies. Tag across all relevant facets — a single recipe usually has
a Course, one or more Main Ingredients, and often a Cuisine, Dish Type, Method,
or Occasion.

Rules:
- Only use slugs from the taxonomy. Never invent a slug.
- Always assign at least one Course.
- Assign a Main Ingredient for the protein(s) or defining ingredient; use
  "vegetarian" only when the dish contains no meat or seafood.
- Assign a Cuisine only when the dish is clearly of that cuisine.
- Assign Method & Equipment only when the recipe genuinely uses it (e.g. a slow
  cooker or Instant Pot).
- Assign Occasion only when the recipe is specifically tied to it.
- Be accurate over exhaustive: include a category only if it clearly fits.

TAXONOMY (facet: slug — Title):
{$taxonomyText}
PROMPT;
    }

    private function recipePrompt(Entry $entry): string
    {
        $title = $entry->title;
        $description = trim((string)($entry->getFieldValue('description') ?: ''));
        $ingredients = $this->nutrition->buildIngredientStrings($entry);
        $ingredientText = $ingredients ? "- " . implode("\n- ", $ingredients) : '(none listed)';

        $current = array_map(fn($c) => $c->slug, $entry->getFieldValue('categories')->all());
        $currentText = $current ? implode(', ', $current) : '(none)';

        $parts = ["Recipe: {$title}"];
        if ($description !== '') {
            $parts[] = "Description: {$description}";
        }
        $parts[] = "Ingredients:\n{$ingredientText}";
        $parts[] = "Current tags (may be incomplete or wrong): {$currentText}";
        $parts[] = "\nReturn every taxonomy slug that applies.";

        return implode("\n\n", $parts);
    }

    /**
     * Build the taxonomy prompt text + the flat list of taggable slugs.
     *
     * @return array{prompt:string, slugs:string[]}
     */
    private function getTaxonomy(): array
    {
        if ($this->taxonomy !== null) {
            return $this->taxonomy;
        }

        $facets = Entry::find()
            ->section(self::CATEGORY_SECTION)
            ->level(1)
            ->orderBy('lft asc')
            ->all();

        $lines = [];
        $slugs = [];

        foreach ($facets as $facet) {
            $leaves = Entry::find()
                ->section(self::CATEGORY_SECTION)
                ->descendantOf($facet)
                ->status('enabled')
                ->orderBy('lft asc')
                ->all();

            foreach ($leaves as $leaf) {
                $lines[] = "{$facet->title}: {$leaf->slug} — {$leaf->title}";
                $slugs[] = $leaf->slug;
            }
        }

        return $this->taxonomy = ['prompt' => implode("\n", $lines), 'slugs' => $slugs];
    }

    // --- HTTP ---------------------------------------------------------------

    /**
     * @param string[] $headers
     * @return array{success:bool, message:string, data:array}
     */
    private function post(string $url, array $headers, array $payload): array
    {
        $maxAttempts = 4;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 90,
            ]);

            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // Retry transient failures (rate limits, timeouts, 5xx) with backoff.
            $transient = $error !== '' || $httpCode === 429 || $httpCode >= 500;
            if ($transient && $attempt < $maxAttempts) {
                sleep(15 * $attempt); // 15s, 30s, 45s
                continue;
            }

            if ($error) {
                return ['success' => false, 'message' => "cURL error: $error", 'data' => []];
            }

            $data = json_decode($body, true);

            if ($httpCode !== 200) {
                $msg = $data['error']['message'] ?? "HTTP $httpCode";
                return ['success' => false, 'message' => "API error: $msg", 'data' => []];
            }

            return ['success' => true, 'message' => 'ok', 'data' => $data ?: []];
        }

        return ['success' => false, 'message' => 'Exhausted retries', 'data' => []];
    }

    /**
     * Keep only slugs that are actually in the taxonomy.
     *
     * @param mixed $slugs
     * @param string[] $allowed
     * @return string[]
     */
    private function filterSlugs($slugs, array $allowed): array
    {
        $allowedSet = array_flip($allowed);
        $slugs = array_filter((array)$slugs, fn($s) => is_string($s) && isset($allowedSet[$s]));
        return array_values(array_unique($slugs));
    }
}
