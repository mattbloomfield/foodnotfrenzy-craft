<?php

namespace MattBloomfield\RecipeHelper\console\commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\Elements;
use MattBloomfield\RecipeHelper\services\CategorizationService;

/**
 * Auto-tag recipes against the faceted taxonomy using Claude.
 *
 * Usage:
 *   php craft recipe-helper/categorize/run --dry-run --limit=5
 *   php craft recipe-helper/categorize/run
 *   php craft recipe-helper/categorize/run --entry-id=123
 *   php craft recipe-helper/categorize/run --model=claude-haiku-4-5
 *   php craft recipe-helper/categorize/run --replace     (overwrite existing tags)
 */
class CategorizeCommand extends RecipeHelperCommand
{
    use CraftCommand;

    protected $signature = 'recipe-helper:categorize:run';

    protected $description = 'Auto-tag recipes against the faceted taxonomy using an LLM.';

    protected $aliases = ['recipe-helper/categorize/run'];

    protected function defineOptions(): void
    {
        $this->flag('dry-run', 'Show proposed tags without saving.');
        $this->valueOption('entry-id', 'Only this recipe.');
        $this->valueOption('limit', 'Max recipes to process (0 = all).', 0);
        $this->valueOption('delay', 'Seconds to wait between API calls.', 1);
        $this->valueOption('model', 'Model to use. "gemini-*" → Gemini, else Claude.', 'gemini-flash-lite-latest');
        $this->flag('replace', 'Replace existing categories instead of adding to them.');
        $this->flag('force', 'Re-tag recipes that already have categories (default skips fully-tagged only when they have 3+).');
        $this->valueOption('max-existing', 'Only process recipes with at most this many existing categories (-1 = no filter).', -1);
    }

    public function handle(): int
    {
        $dryRun = $this->flagValue('dry-run');
        $entryId = $this->intOption('entry-id');
        $limit = (int)$this->option('limit');
        $delay = (int)$this->option('delay');
        $model = (string)$this->option('model');
        $replace = $this->flagValue('replace');
        $maxExisting = (int)$this->option('max-existing');

        $service = new CategorizationService();

        $query = Entry::find()->section('recipes')->status(null)->orderBy('title');
        if ($entryId) {
            $query->id($entryId);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $recipes = $query->all();
        $total = count($recipes);
        if ($total === 0) {
            $this->stdout("No recipes found.\n");
            return self::SUCCESS;
        }

        // slug → category entry id, for applying suggestions.
        $slugToId = [];
        foreach (Entry::find()->section('categories')->status(null)->all() as $cat) {
            if ($cat->slug) {
                $slugToId[$cat->slug] = $cat->id;
            }
        }

        $this->stdout(sprintf(
            "%sTagging %d recipe(s) with model %s%s\n\n",
            $dryRun ? 'DRY RUN — ' : '',
            $total,
            $model,
            $replace ? ' (replace mode)' : ' (additive)'
        ), 'yellow');

        $ok = 0;
        $failed = 0;

        foreach ($recipes as $i => $recipe) {
            $existingCount = count($recipe->getFieldValue('categories')->all());
            if ($maxExisting >= 0 && $existingCount > $maxExisting) {
                continue; // already sufficiently tagged — skip without an API call
            }

            $this->stdout(sprintf("[%d/%d] %s\n", $i + 1, $total, $recipe->title));

            $result = $service->categorize($recipe, $model);
            if (!$result['success']) {
                $this->stdout("  ✗ {$result['message']}\n", 'red');
                $failed++;
                continue;
            }

            $suggested = $result['slugs'];
            $existing = array_map(fn($c) => $c->slug, $recipe->getFieldValue('categories')->all());

            $finalSlugs = $replace
                ? $suggested
                : array_values(array_unique(array_merge($existing, $suggested)));

            $added = array_values(array_diff($finalSlugs, $existing));
            $this->stdout('  suggested: ' . (implode(', ', $suggested) ?: '(none)') . "\n");
            $this->stdout('  + adding:  ' . (implode(', ', $added) ?: '(nothing new)') . "\n", 'green');

            $shouldSave = !$dryRun && (count($added) > 0 || $replace);
            if ($shouldSave) {
                $ids = array_values(array_filter(array_map(fn($s) => $slugToId[$s] ?? null, $finalSlugs)));
                $recipe->setFieldValue('categories', $ids);
                if (!Elements::saveElement($recipe)) {
                    $errors = implode(', ', $recipe->getFirstErrors());
                    $this->stdout("  ✗ save failed: $errors\n", 'red');
                    $failed++;
                    continue;
                }
            }

            $ok++;

            if ($delay > 0 && $i < $total - 1) {
                sleep($delay);
            }
        }

        $this->stdout(sprintf("\nDone. tagged=%d failed=%d%s\n", $ok, $failed,
            $dryRun ? ' (dry run — nothing saved)' : ''), 'green');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
