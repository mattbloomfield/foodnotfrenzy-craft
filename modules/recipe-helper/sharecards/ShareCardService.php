<?php

namespace MattBloomfield\RecipeHelper\sharecards;

use CraftCms\Cms\Asset\Elements\Asset;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Entry\Elements\Entry;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Decides what each page's share card says and caches the rendered JPEGs.
 *
 * Cards are addressed by a key: `entry-<id>` for entries, `page-<name>` for
 * template-only pages (latest, search…). The URL carries a version hash of the
 * card's content, so social networks re-fetch when a recipe changes.
 */
final class ShareCardService
{
    /** Bump to re-render every card after changing the design. */
    private const DESIGN_VERSION = 1;

    private const SITE = 'Food Not Frenzy';
    private const FOOTER = 'foodnotfrenzy.com';

    private const PAGES = [
        'latest' => ['kicker' => self::SITE, 'title' => 'Latest Recipes', 'recipeCount' => true],
        'search' => ['kicker' => self::SITE, 'title' => 'Search Recipes', 'recipeCount' => true],
        'default' => ['kicker' => self::SITE, 'title' => 'Real recipes, without the ad-filled frenzy', 'recipeCount' => true],
    ];

    public function __construct(private readonly ShareCardRenderer $renderer = new ShareCardRenderer()) {}

    /** Absolute URL of the card for an entry (or a named page when there's no entry). */
    public function url(?ElementInterface $element, string $page = 'default'): string
    {
        $key = $element instanceof Entry && $element->id ? "entry-$element->id" : 'page-' . (isset(self::PAGES[$page]) ? $page : 'default');
        $spec = $this->spec($key);

        return url("share-card/$key") . '?v=' . ($spec ? $this->version($spec) : '0');
    }

    /** Alt text describing the card, for og:image:alt / twitter:image:alt. */
    public function alt(?ElementInterface $element, string $page = 'default'): string
    {
        $key = $element instanceof Entry && $element->id ? "entry-$element->id" : 'page-' . (isset(self::PAGES[$page]) ? $page : 'default');
        $spec = $this->spec($key);

        return $spec ? trim($spec['card']['title'] . ' — ' . self::SITE) : self::SITE;
    }

    /**
     * Path to the rendered JPEG for a key, rendering and caching it if needed.
     * Null if the key doesn't resolve to a live entry or known page.
     */
    public function path(string $key): ?string
    {
        $spec = $this->spec($key);
        if (!$spec) {
            return null;
        }

        $dir = storage_path('app/share-cards');
        $path = "$dir/$key-" . $this->version($spec) . '.jpg';
        if (is_file($path)) {
            return $path;
        }

        File::ensureDirectoryExists($dir, 0775);
        $photo = null;
        if ($spec['photo']) {
            try {
                $photo = $spec['photo']->getContents();
            } catch (Throwable $e) {
                report($e);   // missing file etc.: fall back to the brand panel
            }
        }
        $bytes = $this->renderer->render($spec['card'], $photo);

        // Replace older versions of this card.
        foreach (glob("$dir/$key-*.jpg") ?: [] as $old) {
            @unlink($old);
        }
        $tmp = "$path." . getmypid() . '.tmp';
        file_put_contents($tmp, $bytes);
        rename($tmp, $path);

        return $path;
    }

    /** @return array{card: array, photo: ?Asset}|null */
    private function spec(string $key): ?array
    {
        static $memo = [];
        if (array_key_exists($key, $memo)) {
            return $memo[$key];
        }

        if (preg_match('/^page-([a-z]+)$/', $key, $m)) {
            return $memo[$key] = isset(self::PAGES[$m[1]]) ? $this->pageSpec(self::PAGES[$m[1]]) : null;
        }
        if (!preg_match('/^entry-(\d+)$/', $key, $m)) {
            return $memo[$key] = null;
        }

        /** @var Entry|null $entry */
        $entry = Entry::find()->id((int)$m[1])->status('live')->one();
        if (!$entry) {
            return $memo[$key] = null;
        }

        return $memo[$key] = match ($entry->section?->handle) {
            'recipes' => $this->recipeSpec($entry),
            'categories' => $this->categorySpec($entry),
            'home' => $this->pageSpec(self::PAGES['default']),
            'categoryListing' => $this->card(self::SITE, 'Recipe Categories', [$this->recipeCountFact()]),
            'about' => $this->card(self::SITE, (string)$entry->title, []),
            default => $this->card(self::SITE, (string)$entry->title, []),
        };
    }

    private function recipeSpec(Entry $recipe): array
    {
        $categories = $recipe->categories->all();
        $course = $this->facetValue($categories, 'course') ?? $this->facetValue($categories, 'dish-type');

        $minutes = (int)($recipe->prepTime ?? 0) + (int)($recipe->cookTime ?? 0);
        $servings = $recipe->servings !== null ? (float)$recipe->servings : null;
        $facts = array_values(array_filter([
            $minutes > 0 ? $this->formatMinutes($minutes) : null,
            $servings ? 'Serves ' . rtrim(rtrim(number_format($servings, 1, '.', ''), '0'), '.') : null,
        ]));

        return $this->card($course ? "Recipe · $course" : 'Recipe', (string)$recipe->title, $facts, $this->photo($recipe));
    }

    private function categorySpec(Entry $category): array
    {
        $parent = $category->getParent();
        // Recipes are tagged with leaf values (Dessert), not facets (Course): count the whole subtree.
        $targets = [$category, ...$category->getDescendants()->all()];
        $count = Entry::find()->section('recipes')->relatedTo($targets)->count();
        $facts = [$count === 1 ? '1 recipe' : "$count recipes"];

        if (!$parent) {
            // A facet (Course, Cuisine…).
            return $this->card('Browse by', (string)$category->title, $facts, $this->photo($category));
        }

        return $this->card((string)$parent->title, $category->title . ' Recipes', $facts, $this->photo($category));
    }

    private function pageSpec(array $page): array
    {
        return $this->card($page['kicker'], $page['title'], $page['recipeCount'] ? [$this->recipeCountFact()] : []);
    }

    private function card(string $kicker, string $title, array $facts, ?Asset $photo = null): array
    {
        return [
            'card' => [
                'kicker' => $kicker,
                'title' => html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'facts' => $facts,
                'footer' => self::FOOTER,
                'wordmark' => self::SITE,
            ],
            'photo' => $photo,
        ];
    }

    private function photo(Entry $entry): ?Asset
    {
        try {
            return $entry->image?->kind('image')->one();
        } catch (Throwable) {
            return null;   // entry type without an image field
        }
    }

    /** Title of the category under the given top-level facet (e.g. course → "Dessert"). */
    private function facetValue(array $categories, string $facetSlug): ?string
    {
        foreach ($categories as $category) {
            if ($category->getParent()?->slug === $facetSlug) {
                return (string)$category->title;
            }
        }

        return null;
    }

    private function recipeCountFact(): string
    {
        return Entry::find()->section('recipes')->count() . ' recipes';
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        $parts = [];
        if ($hours) {
            $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
        }
        if ($rest) {
            $parts[] = "$rest min";
        }

        return implode(' ', $parts);
    }

    private function version(array $spec): string
    {
        $photo = $spec['photo'];

        return substr(md5(json_encode([
            self::DESIGN_VERSION,
            $spec['card'],
            $photo?->id,
            $photo?->dateModified?->format('U'),
        ])), 0, 12);
    }
}
