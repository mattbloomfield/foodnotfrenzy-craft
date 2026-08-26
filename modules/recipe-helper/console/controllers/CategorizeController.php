<?php

namespace MattBloomfield\RecipeHelper\console\controllers;

use Craft;
use craft\console\Controller;
use craft\elements\Entry;
use craft\helpers\ArrayHelper;
use MattBloomfield\RecipeHelper\services\CategorizationService;
use yii\console\ExitCode;
use yii\helpers\Console;

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
class CategorizeController extends Controller
{
    /** @var bool Show proposed tags without saving. */
    public bool $dryRun = false;

    /** @var int|null Only this recipe. */
    public ?int $entryId = null;

    /** @var int Max recipes to process (0 = all). */
    public int $limit = 0;

    /** @var int Seconds to wait between API calls. */
    public int $delay = 1;

    /** @var string Model to use. "gemini-*" → Gemini, else Claude. */
    public string $model = 'gemini-flash-lite-latest';

    /** @var bool Replace existing categories instead of adding to them. */
    public bool $replace = false;

    /** @var bool Re-tag recipes that already have categories (default skips fully-tagged only when they have 3+). */
    public bool $force = false;

    public function options($actionID): array
    {
        $options = parent::options($actionID);
        return array_merge($options, ['dryRun', 'entryId', 'limit', 'delay', 'model', 'replace', 'force']);
    }

    public function actionRun(): int
    {
        $service = new CategorizationService();

        $query = Entry::find()->section('recipes')->status(null)->orderBy('title asc');
        if ($this->entryId) {
            $query->id($this->entryId);
        }
        if ($this->limit > 0) {
            $query->limit($this->limit);
        }

        $recipes = $query->all();
        $total = count($recipes);
        if ($total === 0) {
            $this->stdout("No recipes found.\n");
            return ExitCode::OK;
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
            $this->dryRun ? 'DRY RUN — ' : '',
            $total,
            $this->model,
            $this->replace ? ' (replace mode)' : ' (additive)'
        ), Console::FG_YELLOW);

        $ok = 0;
        $failed = 0;

        foreach ($recipes as $i => $recipe) {
            $this->stdout(sprintf("[%d/%d] %s\n", $i + 1, $total, $recipe->title));

            $result = $service->categorize($recipe, $this->model);
            if (!$result['success']) {
                $this->stdout("  ✗ {$result['message']}\n", Console::FG_RED);
                $failed++;
                continue;
            }

            $suggested = $result['slugs'];
            $existing = array_map(fn($c) => $c->slug, $recipe->getFieldValue('categories')->all());

            $finalSlugs = $this->replace
                ? $suggested
                : array_values(array_unique(array_merge($existing, $suggested)));

            $added = array_values(array_diff($finalSlugs, $existing));
            $this->stdout('  suggested: ' . (implode(', ', $suggested) ?: '(none)') . "\n");
            $this->stdout('  + adding:  ' . (implode(', ', $added) ?: '(nothing new)') . "\n", Console::FG_GREEN);

            $shouldSave = !$this->dryRun && (count($added) > 0 || $this->replace);
            if ($shouldSave) {
                $ids = array_values(array_filter(array_map(fn($s) => $slugToId[$s] ?? null, $finalSlugs)));
                $recipe->setFieldValue('categories', $ids);
                if (!Craft::$app->getElements()->saveElement($recipe)) {
                    $errors = implode(', ', $recipe->getFirstErrors());
                    $this->stdout("  ✗ save failed: $errors\n", Console::FG_RED);
                    $failed++;
                    continue;
                }
            }

            $ok++;

            if ($this->delay > 0 && $i < $total - 1) {
                sleep($this->delay);
            }
        }

        $this->stdout(sprintf("\nDone. tagged=%d failed=%d%s\n", $ok, $failed,
            $this->dryRun ? ' (dry run — nothing saved)' : ''), Console::FG_GREEN);

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }
}
