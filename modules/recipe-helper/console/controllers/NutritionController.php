<?php

namespace MattBloomfield\RecipeHelper\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use MattBloomfield\RecipeHelper\services\NutritionService;
use yii\console\ExitCode;

class NutritionController extends Controller
{
    /**
     * @var bool Recalculate even if nutrition data already exists.
     */
    public bool $force = false;

    /**
     * @var int|null Process a single recipe by entry ID.
     */
    public ?int $entryId = null;

    /**
     * @var int Seconds to wait between API calls (rate limiting).
     */
    public int $delay = 2;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'force';
        $options[] = 'entryId';
        $options[] = 'delay';
        return $options;
    }

    /**
     * Calculate nutrition data for recipe entries.
     *
     * Usage:
     *   php craft recipe-helper/nutrition/calculate
     *   php craft recipe-helper/nutrition/calculate --force
     *   php craft recipe-helper/nutrition/calculate --entry-id=123
     *   php craft recipe-helper/nutrition/calculate --delay=5
     */
    public function actionCalculate(): int
    {
        $query = Entry::find()->type('recipe')->status(null);

        if ($this->entryId) {
            $query->id($this->entryId);
        }

        $entries = $query->all();
        $total = count($entries);

        if ($total === 0) {
            $this->stdout("No recipe entries found.\n");
            return ExitCode::OK;
        }

        $this->stdout("Processing $total recipe(s)...\n\n");

        $service = new NutritionService();
        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($entries as $i => $entry) {
            $num = $i + 1;

            if (!$this->force && $entry->getFieldValue('nutritionCalories')) {
                $this->stdout("[$num/$total] Skipping \"{$entry->title}\" — already has nutrition data\n");
                $skipped++;
                continue;
            }

            $this->stdout("[$num/$total] Processing \"{$entry->title}\"... ");

            $result = $service->calculateAndSave($entry);

            if ($result['success']) {
                $this->stdout("OK\n");
                $processed++;
            } else {
                $this->stderr("FAILED: {$result['message']}\n");
                $errors++;
            }

            // Rate limit — don't sleep after the last entry
            if ($i < $total - 1 && $this->delay > 0) {
                sleep($this->delay);
            }
        }

        $this->stdout("\nDone. Processed: $processed, Skipped: $skipped, Errors: $errors\n");

        return $errors > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
