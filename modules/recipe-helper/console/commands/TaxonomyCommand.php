<?php

namespace MattBloomfield\RecipeHelper\console\commands;

use CraftCms\Cms\Console\CraftCommand;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Support\Facades\ElementCaches;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sections;
use CraftCms\Cms\Support\Facades\Structures;

/**
 * Reorganizes the flat `categories` structure into a faceted taxonomy:
 * top-level facet groupers (Course, Cuisine, Main Ingredient, Method &
 * Equipment, Occasion, Dish Type) with taggable categories nested beneath.
 *
 * Idempotent: safe to run repeatedly and on each environment. Re-parenting an
 * existing category entry preserves its id, so recipe→category relations carry
 * over automatically; only true merges rewrite relations.
 *
 * Usage:
 *   php craft recipe-helper/taxonomy/migrate --dry-run
 *   php craft recipe-helper/taxonomy/migrate
 */
class TaxonomyCommand extends RecipeHelperCommand
{
    use CraftCommand;

    protected $signature = 'recipe-helper:taxonomy:migrate';

    protected $description = 'Reorganize the categories section into the faceted taxonomy.';

    protected $aliases = ['recipe-helper/taxonomy/migrate'];

    /** @var bool Print the plan without changing anything. */
    private bool $dryRun = false;

    private const CATEGORY_FIELD_HANDLE = 'categories';
    private const CATEGORY_SECTION = 'categories';

    /** @var array<string,Entry> Existing category entries keyed by slug. */
    private array $bySlug = [];

    private int $created = 0;
    private int $moved = 0;
    private int $merged = 0;
    private int $deleted = 0;

    protected function defineOptions(): void
    {
        $this->flag('dry-run', 'Print the plan without changing anything.');
    }

    /**
     * The target taxonomy. Each node:
     *   title    display title
     *   slug     url slug (kept stable where possible)
     *   from     existing slug to reuse/rename in place (optional)
     *   merge    existing slugs whose recipe relations fold into this node,
     *            then get deleted (optional)
     *   children nested nodes (optional)
     */
    private function spec(): array
    {
        return [
            ['title' => 'Course', 'slug' => 'course', 'children' => [
                ['title' => 'Breakfast', 'slug' => 'breakfast', 'from' => 'breakfast'],
                ['title' => 'Main Dish', 'slug' => 'main-dish', 'from' => 'main-dish', 'merge' => ['lunch']],
                ['title' => 'Side Dish', 'slug' => 'side-dish', 'from' => 'sides'],
                ['title' => 'Appetizer & Snack', 'slug' => 'appetizers-snacks', 'from' => 'appetizers-snacks', 'merge' => ['snack']],
                ['title' => 'Soup & Chili', 'slug' => 'soup-and-chili', 'from' => 'soup-and-chili'],
                ['title' => 'Salad', 'slug' => 'salads', 'from' => 'salads'],
                ['title' => 'Dessert', 'slug' => 'desserts', 'from' => 'desserts', 'children' => [
                    ['title' => 'Cake', 'slug' => 'cakes', 'from' => 'cakes'],
                    ['title' => 'Cookies & Bars', 'slug' => 'cookies-bars', 'from' => 'cookies-bars'],
                    ['title' => 'Pie', 'slug' => 'pies', 'from' => 'pies'],
                    ['title' => 'Crisp & Cobbler', 'slug' => 'crisps-cobblers', 'from' => 'crisps-cobblers'],
                    ['title' => 'Sweet Rolls', 'slug' => 'sweet-rolls', 'from' => 'dessert-rolls'],
                ]],
            ]],
            ['title' => 'Cuisine', 'slug' => 'cuisine', 'from' => 'cultural', 'children' => [
                ['title' => 'Asian', 'slug' => 'asian', 'from' => 'asian'],
                ['title' => 'Mexican', 'slug' => 'mexican', 'from' => 'mexican'],
                ['title' => 'Swedish', 'slug' => 'swedish', 'from' => 'swedish'],
            ]],
            ['title' => 'Main Ingredient', 'slug' => 'main-ingredient', 'children' => [
                ['title' => 'Chicken', 'slug' => 'chicken', 'from' => 'chicken'],
                ['title' => 'Beef', 'slug' => 'beef', 'from' => 'beef'],
                ['title' => 'Pork', 'slug' => 'pork', 'from' => 'pork'],
                ['title' => 'Seafood', 'slug' => 'seafood', 'from' => 'seafood'],
                ['title' => 'Vegetarian', 'slug' => 'vegetarian', 'from' => 'meatless'],
            ]],
            ['title' => 'Method & Equipment', 'slug' => 'method', 'children' => [
                ['title' => 'Slow Cooker', 'slug' => 'slow-cooker', 'from' => 'crockpot'],
                ['title' => 'Instant Pot', 'slug' => 'instant-pot', 'from' => 'instant-pot'],
            ]],
            ['title' => 'Occasion', 'slug' => 'occasion', 'from' => 'holidays', 'children' => [
                ['title' => 'Christmas', 'slug' => 'christmas', 'from' => 'christmas'],
                ['title' => 'Thanksgiving', 'slug' => 'thanksgiving', 'from' => 'thanksgiving'],
            ]],
            ['title' => 'Dish Type', 'slug' => 'dish-type', 'children' => [
                ['title' => 'Bread', 'slug' => 'bread', 'from' => 'breads', 'children' => [
                    ['title' => 'Muffins', 'slug' => 'muffins', 'from' => 'muffins'],
                    ['title' => 'Rolls', 'slug' => 'rolls', 'from' => 'rolls'],
                    ['title' => 'Loaves', 'slug' => 'loaves', 'from' => 'loaves'],
                ]],
                ['title' => 'Pasta', 'slug' => 'pasta', 'from' => 'pasta'],
                ['title' => 'Pizza', 'slug' => 'pizza', 'from' => 'pizza'],
                ['title' => 'Sandwiches', 'slug' => 'sandwiches', 'from' => 'sandwiches-2'],
                ['title' => 'Dips, Dressings & Sauces', 'slug' => 'dips', 'from' => 'dips'],
            ]],
        ];
    }

    /** Slugs to delete outright (empty / redundant). */
    private function deletions(): array
    {
        return ['tarts', 'donuts'];
    }

    public function handle(): int
    {
        $this->dryRun = $this->flagValue('dry-run');

        $section = Sections::getSectionByHandle(self::CATEGORY_SECTION);
        if (!$section) {
            $this->stderr("Categories section not found.\n");
            return self::FAILURE;
        }
        $this->structureId = $section->structureId;
        $this->sectionId = $section->id;
        $this->typeId = $section->getEntryTypes()[0]->id;

        $this->loadExisting();

        if ($this->dryRun) {
            $this->stdout("DRY RUN — no changes will be saved.\n\n", 'yellow');
        }

        // Build the tree, top-down so parents exist before children.
        foreach ($this->spec() as $facet) {
            $this->processNode($facet, null);
        }

        // Delete redundant leftovers + any orphaned/empty nodes.
        foreach ($this->deletions() as $slug) {
            $this->deleteCategory($slug, 'redundant');
        }
        $this->deleteOrphans();

        // Regenerate URIs (parent slugs changed) + search index.
        if (!$this->dryRun) {
            $this->stdout("\nResaving categories to refresh URIs…\n");
            foreach (Entry::find()->section(self::CATEGORY_SECTION)->status(null)->all() as $cat) {
                Elements::saveElement($cat, true, true, true);
            }
            ElementCaches::invalidateForElementType(Entry::class);
        }

        $this->stdout(sprintf(
            "\nDone. created=%d moved=%d merged(recipes)=%d deleted=%d\n",
            $this->created, $this->moved, $this->merged, $this->deleted
        ), 'green');

        return self::SUCCESS;
    }

    private int $structureId = 0;
    private int $sectionId = 0;
    private int $typeId = 0;

    private function loadExisting(): void
    {
        $this->bySlug = [];
        foreach (Entry::find()->section(self::CATEGORY_SECTION)->status(null)->all() as $entry) {
            if ($entry->slug) {
                $this->bySlug[$entry->slug] = $entry;
            }
        }
    }

    private function processNode(array $node, ?Entry $parent): void
    {
        $entry = $this->resolveEntry($node);
        $this->placeUnder($entry, $parent);

        // Fold merged categories' recipes into this entry, then delete them.
        foreach ($node['merge'] ?? [] as $mergeSlug) {
            $this->mergeInto($mergeSlug, $entry);
        }

        foreach ($node['children'] ?? [] as $child) {
            $this->processNode($child, $entry);
        }
    }

    /**
     * Find (by `from` or target slug) or create the entry, then retitle/reslug.
     */
    private function resolveEntry(array $node): Entry
    {
        $existing = null;
        if (!empty($node['from']) && isset($this->bySlug[$node['from']])) {
            $existing = $this->bySlug[$node['from']];
        } elseif (isset($this->bySlug[$node['slug']])) {
            $existing = $this->bySlug[$node['slug']];
        }

        if ($existing) {
            $changed = ($existing->title !== $node['title']) || ($existing->slug !== $node['slug']);
            if ($changed) {
                $this->stdout(sprintf("  rename  %-24s → %-24s (%s)\n",
                    "{$existing->title} [{$existing->slug}]", $node['title'], $node['slug']));
            }
            if (!$this->dryRun && $changed) {
                $existing->title = $node['title'];
                $existing->slug = $node['slug'];
                Elements::saveElement($existing);
            }
            // Keep the lookup coherent under the new slug.
            $existing->title = $node['title'];
            $existing->slug = $node['slug'];
            $this->bySlug[$node['slug']] = $existing;
            return $existing;
        }

        $this->stdout(sprintf("  create  %s [%s]\n", $node['title'], $node['slug']));
        $this->created++;

        $entry = new Entry();
        $entry->sectionId = $this->sectionId;
        $entry->typeId = $this->typeId;
        $entry->title = $node['title'];
        $entry->slug = $node['slug'];
        $entry->enabled = true;

        if (!$this->dryRun) {
            Elements::saveElement($entry);
        }
        $this->bySlug[$node['slug']] = $entry;
        return $entry;
    }

    private function placeUnder(Entry $entry, ?Entry $parent): void
    {
        if ($this->dryRun) {
            return;
        }
        if ($parent === null) {
            Structures::appendToRoot($this->structureId, $entry);
        } else {
            Structures::append($this->structureId, $entry, $parent);
        }
        $this->moved++;
    }

    /**
     * Move all recipe relations from $sourceSlug onto $target, then delete the
     * now-empty source category. Uses element saves so caches/URIs stay correct.
     */
    private function mergeInto(string $sourceSlug, Entry $target): void
    {
        $source = $this->bySlug[$sourceSlug] ?? null;
        if (!$source || $source->id === $target->id) {
            return;
        }

        $recipes = Entry::find()
            ->section('recipes')
            ->relatedTo(['targetElement' => $source, 'field' => self::CATEGORY_FIELD_HANDLE])
            ->status(null)
            ->all();

        $this->stdout(sprintf("  merge   %s → %s (%d recipes)\n",
            $sourceSlug, $target->slug, count($recipes)));

        if (!$this->dryRun) {
            foreach ($recipes as $recipe) {
                $ids = array_map(fn(Entry $category) => $category->id, $recipe->getFieldValue(self::CATEGORY_FIELD_HANDLE)->all());
                $ids = array_values(array_unique(array_map(
                    fn($id) => (int)$id === (int)$source->id ? (int)$target->id : (int)$id,
                    $ids
                )));
                $recipe->setFieldValue(self::CATEGORY_FIELD_HANDLE, $ids);
                Elements::saveElement($recipe);
                $this->merged++;
            }
        } else {
            $this->merged += count($recipes);
        }

        $this->deleteEntry($source, 'merged');
        unset($this->bySlug[$sourceSlug]);
    }

    private function deleteCategory(string $slug, string $reason): void
    {
        $entry = $this->bySlug[$slug] ?? null;
        if ($entry) {
            $this->deleteEntry($entry, $reason);
            unset($this->bySlug[$slug]);
        }
    }

    /**
     * Remove leftover cruft: entries with empty titles or __temp slugs, and any
     * category not accounted for by the spec that has zero recipe relations.
     */
    private function deleteOrphans(): void
    {
        foreach (Entry::find()->section(self::CATEGORY_SECTION)->status(null)->all() as $entry) {
            $isTemp = !$entry->title || str_starts_with((string)$entry->slug, '__temp');
            if ($isTemp) {
                $this->deleteEntry($entry, 'orphan');
            }
        }
    }

    private function deleteEntry(Entry $entry, string $reason): void
    {
        $this->stdout(sprintf("  delete  %s [%s] (%s)\n", $entry->title ?: '(untitled)', $entry->slug, $reason));
        $this->deleted++;
        if (!$this->dryRun) {
            Elements::deleteElement($entry);
        }
    }
}
