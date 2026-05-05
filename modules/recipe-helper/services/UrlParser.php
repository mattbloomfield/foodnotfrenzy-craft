<?php

namespace MattBloomfield\RecipeHelper\services;

use Craft;
use GuzzleHttp\Client;

class UrlParser
{
    private IngredientParser $ingredientParser;

    public function __construct()
    {
        $this->ingredientParser = new IngredientParser();
    }

    /**
     * Fetch a URL and extract structured recipe data from JSON-LD.
     *
     * @return array Standardized recipe data
     * @throws \Exception if no Recipe JSON-LD found
     */
    public function parse(string $url): array
    {
        $client = Craft::createGuzzleClient([
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; RecipeImporter/1.0)',
                'Accept' => 'text/html',
            ],
        ]);

        $response = $client->get($url);
        $html = (string) $response->getBody();

        $recipe = $this->extractRecipeJsonLd($html);

        if (!$recipe) {
            throw new \Exception('No Recipe structured data (JSON-LD) found on this page.');
        }

        return $this->normalizeRecipe($recipe, $url);
    }

    /**
     * Find and return the Recipe object from JSON-LD script tags.
     */
    private function extractRecipeJsonLd(string $html): ?array
    {
        // Find all JSON-LD script blocks
        if (!preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $jsonStr) {
            $data = json_decode(trim($jsonStr), true);
            if (!$data) {
                continue;
            }

            // Direct Recipe object
            if ($this->isRecipe($data)) {
                return $data;
            }

            // @graph array
            if (isset($data['@graph']) && is_array($data['@graph'])) {
                foreach ($data['@graph'] as $item) {
                    if ($this->isRecipe($item)) {
                        return $item;
                    }
                }
            }

            // Array of objects
            if (isset($data[0]) && is_array($data[0])) {
                foreach ($data as $item) {
                    if ($this->isRecipe($item)) {
                        return $item;
                    }
                }
            }
        }

        return null;
    }

    private function isRecipe(array $data): bool
    {
        $type = $data['@type'] ?? '';
        if (is_array($type)) {
            return in_array('Recipe', $type);
        }
        return $type === 'Recipe';
    }

    /**
     * Convert raw JSON-LD recipe data into standardized format.
     */
    private function normalizeRecipe(array $raw, string $url): array
    {
        $ingredients = [];
        foreach ($raw['recipeIngredient'] ?? [] as $line) {
            $ingredients[] = $this->ingredientParser->parse($line);
        }

        $instructions = $this->parseInstructions($raw['recipeInstructions'] ?? []);

        $imageUrl = $this->extractImageUrl($raw['image'] ?? null);

        return [
            'title' => $raw['name'] ?? '',
            'description' => strip_tags($raw['description'] ?? ''),
            'imageUrl' => $imageUrl,
            'sourceUrl' => $url,
            'prepTime' => $this->parseIsoDuration($raw['prepTime'] ?? ''),
            'cookTime' => $this->parseIsoDuration($raw['cookTime'] ?? ''),
            'totalTime' => $this->parseIsoDuration($raw['totalTime'] ?? ''),
            'servings' => $this->parseServings($raw['recipeYield'] ?? ''),
            'ingredients' => $ingredients,
            'instructions' => $instructions,
            'notes' => '',
        ];
    }

    /**
     * Parse recipeInstructions which can be strings, HowToStep objects,
     * or HowToSection objects containing HowToStep items.
     *
     * @return array Array of section arrays: [['heading' => '', 'steps' => [...]]]
     */
    private function parseInstructions($raw): array
    {
        if (!is_array($raw)) {
            return [['heading' => '', 'steps' => $raw ? [strip_tags((string) $raw)] : []]];
        }

        $sections = [];
        $currentSteps = [];

        foreach ($raw as $item) {
            if (is_string($item)) {
                $currentSteps[] = strip_tags($item);
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $type = $item['@type'] ?? '';

            if ($type === 'HowToSection') {
                // Save any accumulated steps as an untitled section
                if (!empty($currentSteps)) {
                    $sections[] = ['heading' => '', 'steps' => $currentSteps];
                    $currentSteps = [];
                }

                $sectionSteps = [];
                foreach ($item['itemListElement'] ?? [] as $step) {
                    if (is_string($step)) {
                        $sectionSteps[] = strip_tags($step);
                    } elseif (is_array($step)) {
                        $sectionSteps[] = strip_tags($step['text'] ?? $step['name'] ?? '');
                    }
                }
                $sections[] = [
                    'heading' => $item['name'] ?? '',
                    'steps' => $sectionSteps,
                ];
            } elseif ($type === 'HowToStep') {
                $currentSteps[] = strip_tags($item['text'] ?? $item['name'] ?? '');
            } else {
                // Fallback: treat as text
                $text = $item['text'] ?? $item['name'] ?? '';
                if ($text) {
                    $currentSteps[] = strip_tags($text);
                }
            }
        }

        // Flush remaining steps
        if (!empty($currentSteps)) {
            $sections[] = ['heading' => '', 'steps' => $currentSteps];
        }

        return $sections ?: [['heading' => '', 'steps' => []]];
    }

    /**
     * Parse ISO 8601 duration (PT30M, PT1H15M) to integer minutes.
     */
    private function parseIsoDuration(string $duration): ?int
    {
        if (empty($duration)) {
            return null;
        }

        try {
            $interval = new \DateInterval($duration);
            return ($interval->h * 60) + $interval->i + ($interval->d * 24 * 60);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract servings as integer from recipeYield.
     */
    private function parseServings($yield): ?int
    {
        if (is_array($yield)) {
            $yield = $yield[0] ?? '';
        }

        if (preg_match('/(\d+)/', (string) $yield, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Extract a single image URL from the image field (can be string, array, or ImageObject).
     */
    private function extractImageUrl($image): ?string
    {
        if (is_string($image)) {
            return $image;
        }
        if (is_array($image)) {
            // ImageObject
            if (isset($image['url'])) {
                return $image['url'];
            }
            // Array of images or image objects
            $first = $image[0] ?? null;
            if (is_string($first)) {
                return $first;
            }
            if (is_array($first) && isset($first['url'])) {
                return $first['url'];
            }
        }
        return null;
    }
}
