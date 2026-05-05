<?php

namespace MattBloomfield\RecipeHelper\services;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Client;

class ImageParser
{
    private IngredientParser $ingredientParser;

    public function __construct()
    {
        $this->ingredientParser = new IngredientParser();
    }

    /**
     * Send an image to Claude Haiku to extract recipe data.
     *
     * @param string $filePath Absolute path to the uploaded image
     * @return array Standardized recipe data
     * @throws \Exception on API failure
     */
    public function parse(string $filePath): array
    {
        $apiKey = App::env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new \Exception('ANTHROPIC_API_KEY is not set in .env');
        }

        $imageData = base64_encode(file_get_contents($filePath));
        $mimeType = mime_content_type($filePath) ?: 'image/jpeg';

        $client = Craft::createGuzzleClient(['timeout' => 60]);

        $response = $client->post('https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 4096,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mimeType,
                                    'data' => $imageData,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $this->getPrompt(),
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $body = json_decode((string) $response->getBody(), true);
        $text = $body['content'][0]['text'] ?? '';

        // Extract JSON from the response (may be wrapped in markdown code fences)
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $m)) {
            $text = $m[1];
        }

        $data = json_decode($text, true);
        if (!$data) {
            throw new \Exception('Failed to parse recipe from image. Claude response: ' . substr($text, 0, 500));
        }

        return $this->normalizeResponse($data);
    }

    private function getPrompt(): string
    {
        return <<<'PROMPT'
Extract the recipe from this image. Return ONLY valid JSON with this exact structure, no other text:

{
  "title": "Recipe Name",
  "description": "Brief description",
  "prepTime": null,
  "cookTime": null,
  "servings": null,
  "ingredients": [
    "2 cups all-purpose flour",
    "1 teaspoon salt"
  ],
  "instructions": [
    "Preheat oven to 350°F.",
    "Mix dry ingredients together."
  ],
  "notes": ""
}

Rules:
- prepTime and cookTime are integers in minutes, or null if not specified
- servings is an integer, or null if not specified
- ingredients should be full strings like "2 cups flour" (do not split into parts)
- instructions should be individual steps as strings
- If the recipe has named sections (e.g. "For the sauce"), prefix the first ingredient/instruction in that section with the section name followed by a colon
- Include any notes or tips in the notes field
PROMPT;
    }

    /**
     * Convert Claude's response into the standardized format.
     */
    private function normalizeResponse(array $data): array
    {
        $ingredients = [];
        foreach ($data['ingredients'] ?? [] as $line) {
            $ingredients[] = $this->ingredientParser->parse($line);
        }

        // Parse instructions - handle section prefixes
        $sections = [];
        $currentSteps = [];
        $currentHeading = '';

        foreach ($data['instructions'] ?? [] as $step) {
            // Check for section prefix like "For the sauce: Do something"
            if (preg_match('/^(For .+?|.+? (?:ingredients|instructions)):\s*(.*)$/i', $step, $m)) {
                if (!empty($currentSteps)) {
                    $sections[] = ['heading' => $currentHeading, 'steps' => $currentSteps];
                    $currentSteps = [];
                }
                $currentHeading = $m[1];
                if (!empty($m[2])) {
                    $currentSteps[] = $m[2];
                }
            } else {
                $currentSteps[] = $step;
            }
        }

        if (!empty($currentSteps)) {
            $sections[] = ['heading' => $currentHeading, 'steps' => $currentSteps];
        }

        if (empty($sections)) {
            $sections = [['heading' => '', 'steps' => []]];
        }

        return [
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'imageUrl' => null,
            'sourceUrl' => null,
            'prepTime' => $data['prepTime'] ?? null,
            'cookTime' => $data['cookTime'] ?? null,
            'totalTime' => null,
            'servings' => $data['servings'] ?? null,
            'ingredients' => $ingredients,
            'instructions' => $sections,
            'notes' => $data['notes'] ?? '',
        ];
    }
}
