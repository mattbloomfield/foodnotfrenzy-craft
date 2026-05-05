<?php

namespace MattBloomfield\RecipeHelper\services;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Assets as AssetsHelper;

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
        $section = Craft::$app->getEntries()->getSectionByHandle('recipes');
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
        $entry->setScenario(Entry::SCENARIO_ESSENTIALS);

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

        if (!Craft::$app->getElements()->saveElement($entry)) {
            $errors = $entry->getErrors();
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
     * Find an existing Source entry by domain, or create a new one.
     */
    private function findOrCreateSource(string $url): ?Entry
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return null;
        }

        // Strip www. prefix for the title
        $domain = preg_replace('/^www\./', '', $host);

        // Look for existing source by title
        $source = Entry::find()
            ->section('sources')
            ->title($domain)
            ->one();

        if ($source) {
            return $source;
        }

        // Create new source entry
        $section = Craft::$app->getEntries()->getSectionByHandle('sources');
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

        if (!Craft::$app->getElements()->saveElement($source)) {
            Craft::warning('Could not create source entry: ' . json_encode($source->getErrors()), __METHOD__);
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
            $client = Craft::createGuzzleClient(['timeout' => 15]);
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
            $tempDir = Craft::$app->getPath()->getTempPath();
            $filename = AssetsHelper::prepareAssetName(
                preg_replace('/[^a-zA-Z0-9\s-]/', '', $recipeTitle) . '.' . $ext
            );
            $tempPath = $tempDir . DIRECTORY_SEPARATOR . $filename;
            file_put_contents($tempPath, $imageData);

            // Find volume and root folder
            $volume = Craft::$app->getVolumes()->getVolumeByHandle('images');
            if (!$volume) {
                return null;
            }
            $folder = Craft::$app->getAssets()->getRootFolder($volume->id);

            $asset = new Asset();
            $asset->tempFilePath = $tempPath;
            $asset->setVolumeId($volume->id);
            $asset->newFolderId = $folder->id;
            $asset->filename = $filename;
            $asset->title = $recipeTitle;
            $asset->avoidFilenameConflicts = true;
            $asset->setScenario(Asset::SCENARIO_CREATE);

            if (!Craft::$app->getElements()->saveElement($asset)) {
                Craft::warning('Could not save image asset: ' . json_encode($asset->getErrors()), __METHOD__);
                return null;
            }

            return $asset;
        } catch (\Throwable $e) {
            Craft::warning('Failed to download recipe image: ' . $e->getMessage(), __METHOD__);
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
