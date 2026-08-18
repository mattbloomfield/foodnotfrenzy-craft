<?php
namespace MattBloomfield\RecipeHelper\quantity;

/**
 * Single source of truth for ingredient units: aliases, display names,
 * and when to convert between neighboring units.
 *
 * Canonical keys match the ingredientsList field's select values.
 * `up` promotes to a larger unit when the amount is at/above `minimum`
 * AND the converted amount is displayable as a clean fraction.
 * `down` demotes to a smaller unit when the amount drops below `below`.
 */
final class UnitRegistry
{
    private const UNITS = [
        // Volume
        'tsp' => [
            'singular' => 'teaspoon', 'plural' => 'teaspoons',
            'aliases' => ['teaspoon', 'teaspoons', 't'],
            'up' => ['to' => 'Tbsp', 'factor' => 3, 'minimum' => 3],
        ],
        'Tbsp' => [
            'singular' => 'tablespoon', 'plural' => 'tablespoons',
            'aliases' => ['tablespoon', 'tablespoons'],
            'up' => ['to' => 'C', 'factor' => 16, 'minimum' => 4],
            'down' => ['to' => 'tsp', 'factor' => 3, 'below' => 1],
        ],
        'C' => [
            'singular' => 'cup', 'plural' => 'cups',
            'aliases' => ['cup', 'cups'],
            'down' => ['to' => 'Tbsp', 'factor' => 16, 'below' => 0.25],
        ],
        'fl oz' => [
            'singular' => 'fl oz', 'plural' => 'fl oz',
            'aliases' => ['fluid ounce', 'fluid ounces'],
        ],
        'pt' => ['singular' => 'pint', 'plural' => 'pints', 'aliases' => ['pint', 'pints']],
        'qt' => ['singular' => 'quart', 'plural' => 'quarts', 'aliases' => ['quart', 'quarts']],
        'gal' => ['singular' => 'gallon', 'plural' => 'gallons', 'aliases' => ['gallon', 'gallons']],

        // Weight
        'oz' => [
            'singular' => 'ounce', 'plural' => 'ounces',
            'aliases' => ['ounce', 'ounces'],
            'up' => ['to' => 'lbs', 'factor' => 16, 'minimum' => 16],
        ],
        'lbs' => [
            'singular' => 'pound', 'plural' => 'pounds',
            'aliases' => ['lb', 'pound', 'pounds'],
            'down' => ['to' => 'oz', 'factor' => 16, 'below' => 1],
        ],

        // Small amounts — countable, never converted
        'pn' => ['singular' => 'pinch', 'plural' => 'pinches', 'aliases' => ['pinch', 'pinches']],
        'ds' => ['singular' => 'dash', 'plural' => 'dashes', 'aliases' => ['dash', 'dashes']],
        'dr' => ['singular' => 'drop', 'plural' => 'drops', 'aliases' => ['drop', 'drops']],
        'smdg' => ['singular' => 'smidgen', 'plural' => 'smidgens', 'aliases' => ['smidgen', 'smidgens']],

        // Countable
        'box' => ['singular' => 'box', 'plural' => 'boxes', 'aliases' => ['boxes']],
        'can' => ['singular' => 'can', 'plural' => 'cans', 'aliases' => ['cans']],
        'clove' => ['singular' => 'clove', 'plural' => 'cloves', 'aliases' => ['cloves']],
        'package' => ['singular' => 'package', 'plural' => 'packages', 'aliases' => ['packages', 'pkg']],
        'slice' => ['singular' => 'slice', 'plural' => 'slices', 'aliases' => ['slices']],
        'stalk' => ['singular' => 'stalk', 'plural' => 'stalks', 'aliases' => ['stalks']],
        'stick' => ['singular' => 'stick', 'plural' => 'sticks', 'aliases' => ['sticks']],
        'inch' => ['singular' => 'inch', 'plural' => 'inches', 'aliases' => ['inches', 'in']],
        'large' => ['singular' => 'large', 'plural' => 'large', 'aliases' => []],
        'medium' => ['singular' => 'medium', 'plural' => 'medium', 'aliases' => []],
    ];

    /** @var array<string, string>|null lowercase alias → canonical key */
    private static ?array $aliasMap = null;

    /**
     * Resolve a raw unit string to its canonical key, or null if unknown.
     */
    public static function resolve(string $unit): ?string
    {
        if (self::$aliasMap === null) {
            self::$aliasMap = [];
            foreach (self::UNITS as $key => $def) {
                self::$aliasMap[strtolower($key)] = $key;
                foreach ($def['aliases'] as $alias) {
                    self::$aliasMap[strtolower($alias)] = $key;
                }
            }
        }

        return self::$aliasMap[strtolower(trim($unit))] ?? null;
    }

    public static function displayName(string $key, bool $plural): string
    {
        $def = self::UNITS[$key];
        return $plural ? $def['plural'] : $def['singular'];
    }

    /**
     * Convert to the most readable unit: promote while the amount is large
     * enough and stays a clean fraction, otherwise demote while too small.
     *
     * @return array{0: Rational, 1: string} [amount, canonical unit key]
     */
    public static function optimize(Rational $amount, string $key): array
    {
        while ($up = self::UNITS[$key]['up'] ?? null) {
            if ($amount->toFloat() < $up['minimum']) {
                break;
            }
            $converted = $amount->overInt($up['factor']);
            if (!$converted->isDisplayable()) {
                break;
            }
            $amount = $converted;
            $key = $up['to'];
        }

        while ($down = self::UNITS[$key]['down'] ?? null) {
            if ($amount->toFloat() >= $down['below']) {
                break;
            }
            $amount = $amount->timesInt($down['factor']);
            $key = $down['to'];
        }

        return [$amount, $key];
    }
}
