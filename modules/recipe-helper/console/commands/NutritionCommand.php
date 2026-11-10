<?php

namespace MattBloomfield\RecipeHelper\console\commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Entry\Elements\Entry;
use MattBloomfield\RecipeHelper\services\NutritionService;

/**
 * Calculate nutrition data for recipe entries.
 *
 * Usage:
 *   php craft recipe-helper/nutrition/calculate
 *   php craft recipe-helper/nutrition/calculate --force
 *   php craft recipe-helper/nutrition/calculate --entry-id=123
 *   php craft recipe-helper/nutrition/calculate --delay=5
 */
class NutritionCommand extends RecipeHelperCommand
{
    use CraftCommand;

    protected $signature = 'recipe-helper:nutrition:calculate';

    protected $description = 'Calculate nutrition data for recipe entries.';

    protected $aliases = ['recipe-helper/nutrition/calculate'];

    protected function defineOptions(): void
    {
        $this->flag('force', 'Recalculate even if nutrition data already exists.');
        $this->valueOption('entry-id', 'Process a single recipe by entry ID.');
        $this->valueOption('delay', 'Seconds to wait between API calls (rate limiting).', 2);
    }

    public function handle(): int
    {
        $force = $this->flagValue('force');
        $entryId = $this->intOption('entry-id');
        $delay = (int)$this->option('delay');

        $query = Entry::find()->type('recipe')->status(null);

        if ($entryId) {
            $query->id($entryId);
        }

        $entries = $query->all();
        $total = count($entries);

        if ($total === 0) {
            $this->stdout("No recipe entries found.\n");
            return self::SUCCESS;
        }

        $this->stdout("Processing $total recipe(s)...\n\n");

        $service = new NutritionService();
        $processed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($entries as $i => $entry) {
            $num = $i + 1;

            if (!$force && !$service->needsRecalculation($entry)) {
                $this->stdout("[$num/$total] Skipping \"{$entry->title}\" — ingredients unchanged\n");
                $skipped++;
                continue;
            }

            $this->stdout("[$num/$total] Processing \"{$entry->title}\"... ");

            $result = $service->calculateAndSave($entry, $force);

            if ($result['success']) {
                $this->stdout("OK\n");
                $processed++;
            } else {
                $this->stderr("FAILED: {$result['message']}\n");
                $errors++;
            }

            // Rate limit — don't sleep after the last entry
            if ($i < $total - 1 && $delay > 0) {
                sleep($delay);
            }
        }

        $this->stdout("\nDone. Processed: $processed, Skipped: $skipped, Errors: $errors\n");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
