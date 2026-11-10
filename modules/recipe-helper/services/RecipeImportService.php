<?php

namespace MattBloomfield\RecipeHelper\services;

use CraftCms\Cms\Asset\AssetsHelper;
use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Asset\Validation\AssetRules;
use CraftCms\Cms\Element\Validation\ElementRules;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Folders;
use CraftCms\Cms\Support\Facades\Path;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Volumes;
use Illuminate\Support\Facades\Log;
use MattBloomfield\RecipeHelper\support\HttpClient;

class RecipeImportService
{
    /**
     * Create a disabled draft recipe entry from standardized parsed data.
     *
     * @param array $data Standardized recipe data from UrlParser or ImageParser
     * @return Entry The saved recipe entry
     * @throws \Exception on save failure
     */
    public function createRecipe(array $data): Entry
    {
        $section = Sections::getSectionByHandle('recipes');
        $recipeType = null;
        foreach ($section->getEntryTypes() as $et) {
            if ($et->handle === 'recipe') {
                $recipeType = $et;
                break;
            }
        }

        $entry = new Entry();
        $entry->sectionId = $section->id;
        $entry->typeId = $recipeType->id;
        $entry->title = $data['title'];
        $entry->enabled = false;
        $entry->ruleset->useScenario(ElementRules::SCENARIO_ESSENTIALS);

        $fieldValues = [
            'description' => $data['description'] ?? '',
            'notes' => $data['notes'] ?? '',
        ];

        if (!empty($data['prepTime'])) {
            $fieldValues['prepTime'] = (int) $data['prepTime'];
        }
        if (!empty($data['cookTime'])) {
            $fieldValues['cookTime'] = (int) $data['cookTime'];
        }
        if (!empty($data['servings'])) {
            $fieldValues['servings'] = $data['servings'];
        }

        // Build Matrix field data
        $fieldValues['ingredientsAndInstructions'] = $this->buildMatrixData($data);

        $entry->setFieldValues($fieldValues);

        // Handle categories
        if (!empty($data['categoryKeywords'])) {
            $categoryIds = $this->matchCategories($data['categoryKeywords']);
            if (!empty($categoryIds)) {
                $entry->setFieldValue('categories', $categoryIds);
            }
        }

        // Handle source entry relation
        if (!empty($data['sourceUrl'])) {
            $sourceEntry = $this->findOrCreateSource($data['sourceUrl']);
            if ($sourceEntry) {
                $entry->setFieldValue('source', [$sourceEntry->id]);
            }
        }

        // Handle image asset
        if (!empty($data['imageUrl'])) {
            $asset = $this->downloadAndCreateAsset($data['imageUrl'], $data['title']);
            if ($asset) {
                $entry->setFieldValue('image', [$asset->id]);
            }
        }

        if (!Elements::saveElement($entry)) {
            $errors = $entry->errors()->toArray();
            throw new \Exception('Could not save recipe: ' . json_encode($errors));
        }

        return $entry;
    }

    /**
     * Build the Matrix field data array for ingredientsAndInstructions.
     */
    private function buildMatrixData(array $data): array
    {
        $entries = [];
        $sortOrder = [];
        $counter = 1;

        // Single ingredients block with all ingredients
        if (!empty($data['ingredients'])) {
            $newId = 'new:' . $counter++;
            $sortOrder[] = $newId;
            $entries[$newId] = [
                'type' => 'ingredientsBlock',
                'fields' => [
                    'ingredientsList' => array_map(function ($ing) {
                        return [
                            'col1' => $ing['quantity'],
                            'col2' => $ing['unit'],
                            'col3' => $ing['ingredientName'],
                            'col5' => $ing['notes'] ?? '',
                            'col4' => '',
                        ];
                    }, $data['ingredients']),
                ],
            ];
        }

        // Instructions sections (may have headings)
        foreach ($data['instructions'] ?? [] as $section) {
            if (!empty($section['heading'])) {
                $newId = 'new:' . $counter++;
                $sortOrder[] = $newId;
                $entries[$newId] = [
                    'type' => 'heading',
                    'title' => $section['heading'],
                    'fields' => [],
                ];
            }

            if (!empty($section['steps'])) {
                $newId = 'new:' . $counter++;
                $sortOrder[] = $newId;
                $entries[$newId] = [
                    'type' => 'instructionsBlock',
                    'fields' => [
                        'instructions' => array_map(function ($step) {
                            return ['col1' => $step];
                        }, $section['steps']),
                    ],
                ];
            }
        }

        return [
            'sortOrder' => $sortOrder,
            'entries' => $entries,
        ];
    }

    /**
     * Match category keywords against existing Category entries (case-insensitive).
     *
     * @return int[] Array of matched category entry IDs
     */
    private function matchCategories(array $keywords): array
    {
        if (empty($keywords)) {
            return [];
        }

        // Load all categories once
        $allCategories = Entry::find()
            ->section('categories')
            ->status(null)
            ->all();

        $matched = [];
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            if ($keyword === '') {
                continue;
            }
            foreach ($allCategories as $cat) {
                if (strtolower($cat->title) === $keyword && !in_array($cat->id, $matched)) {
                    $matched[] = $cat->id;
                }
            }
        }

        return $matched;
    }

    /**
     * Find an existing Source entry by matching base domain in linkUrl, or create a new one.
     */
    private function findOrCreateSource(string $url): ?Entry
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        // Strip www. prefix for matching
        $domain = preg_replace('/^www\./', '', $host);

        // Search existing sources by checking if linkUrl contains the domain
        $allSources = Entry::find()
            ->section('sources')
            ->status(null)
            ->all();

        foreach ($allSources as $source) {
            $linkUrl = $source->getFieldValue('linkUrl');
            $linkUrlStr = is_object($linkUrl) ? (string) $linkUrl : ($linkUrl ?? '');
            $sourceHost = parse_url($linkUrlStr, PHP_URL_HOST);
            if ($sourceHost) {
                $sourceDomain = preg_replace('/^www\./', '', $sourceHost);
                if (strcasecmp($sourceDomain, $domain) === 0) {
                    return $source;
                }
            }
        }

        // Also check by title as fallback
        foreach ($allSources as $source) {
            if (strcasecmp($source->title, $domain) === 0) {
                return $source;
            }
        }

        // Create new source entry
        $section = Sections::getSectionByHandle('sources');
        $sourceType = null;
        foreach ($section->getEntryTypes() as $et) {
            if ($et->handle === 'source') {
                $sourceType = $et;
                break;
            }
        }

        $source = new Entry();
        $source->sectionId = $section->id;
        $source->typeId = $sourceType->id;
        $source->title = $domain;
        $source->setFieldValue('linkUrl', $url);

        if (!Elements::saveElement($source)) {
            Log::warning('Could not create source entry: ' . json_encode($source->errors()->toArray()));
            return null;
        }

        return $source;
    }

    /**
     * Download an image URL and create an Asset in the Images volume.
     */
    private function downloadAndCreateAsset(string $imageUrl, string $recipeTitle): ?Asset
    {
        try {
            $client = HttpClient::create(['timeout' => 15]);
            $response = $client->get($imageUrl);
            $imageData = (string) $response->getBody();

            if (empty($imageData)) {
                return null;
            }

            // Determine extension from content type or URL
            $contentType = $response->getHeaderLine('Content-Type');
            $ext = $this->getExtensionFromMime($contentType);
            if (!$ext) {
                $ext = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            }

            // Save to temp file
            $tempDir = Path::temp();
            $filename = AssetsHelper::prepareAssetName(
                preg_replace('/[^a-zA-Z0-9\s-]/', '', $recipeTitle) . '.' . $ext
            );
            $tempPath = $tempDir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($tempPath, $imageData);

            // Find volume and root folder
            $volume = Volumes::getVolumeByHandle('images');
            if (!$volume) {
                return null;
            }
            $folder = Folders::getRootFolderByVolumeId($volume->id);

            $asset = new Asset();
            $asset->tempFilePath = $tempPath;
            $asset->setVolumeId($volume->id);
            $asset->newFolderId = $folder->id;
            $asset->filename = $filename;
            $asset->title = $recipeTitle;
            $asset->avoidFilenameConflicts = true;
            $asset->ruleset->useScenario(AssetRules::SCENARIO_CREATE);

            if (!Elements::saveElement($asset)) {
                Log::warning('Could not save image asset: ' . json_encode($asset->errors()->toArray()));
                return null;
            }

            return $asset;
        } catch (\Throwable $e) {
            Log::warning('Failed to download recipe image: ' . $e->getMessage());
            return null;
        }
    }

    private function getExtensionFromMime(string $mime): ?string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
        ];
        // Content-Type may include charset, e.g. "image/jpeg; charset=utf-8"
        $mime = explode(';', $mime)[0];
        return $map[trim($mime)] ?? null;
    }
}
