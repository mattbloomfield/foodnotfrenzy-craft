<?php

namespace MattBloomfield\RecipeHelper\services;

class IngredientParser
{
    /**
     * Map of common unit names/abbreviations to the stored select values
     * in the ingredientsList table field.
     */
    private const UNIT_MAP = [
        // cup
        'cup' => 'C',
        'cups' => 'C',
        'c' => 'C',
        'c.' => 'C',
        // teaspoon
        'teaspoon' => 'tsp',
        'teaspoons' => 'tsp',
        'tsp' => 'tsp',
        'tsp.' => 'tsp',
        't' => 'tsp',
        // tablespoon
        'tablespoon' => 'Tbsp',
        'tablespoons' => 'Tbsp',
        'tbsp' => 'Tbsp',
        'tbsp.' => 'Tbsp',
        'tbs' => 'Tbsp',
        'tbs.' => 'Tbsp',
        // pound
        'pound' => 'lbs',
        'pounds' => 'lbs',
        'lb' => 'lbs',
        'lb.' => 'lbs',
        'lbs' => 'lbs',
        'lbs.' => 'lbs',
        // ounce
        'ounce' => 'oz',
        'ounces' => 'oz',
        'oz' => 'oz',
        'oz.' => 'oz',
        // fluid ounce
        'fluid ounce' => 'fl oz',
        'fluid ounces' => 'fl oz',
        'fl oz' => 'fl oz',
        'fl. oz' => 'fl oz',
        'fl. oz.' => 'fl oz',
        // pinch
        'pinch' => 'pn',
        'pinches' => 'pn',
        // dash
        'dash' => 'ds',
        'dashes' => 'ds',
        // pint
        'pint' => 'pt',
        'pints' => 'pt',
        'pt' => 'pt',
        'pt.' => 'pt',
        // quart
        'quart' => 'qt',
        'quarts' => 'qt',
        'qt' => 'qt',
        'qt.' => 'qt',
        // gallon
        'gallon' => 'gal',
        'gallons' => 'gal',
        'gal' => 'gal',
        'gal.' => 'gal',
        // drop
        'drop' => 'dr',
        'drops' => 'dr',
        // smidgeon
        'smidgeon' => 'smdg',
        'smidgen' => 'smdg',
        // other units that match stored values exactly
        'clove' => 'clove',
        'cloves' => 'clove',
        'can' => 'can',
        'cans' => 'can',
        'box' => 'box',
        'boxes' => 'box',
        'inch' => 'inch',
        'inches' => 'inch',
        'large' => 'large',
        'medium' => 'medium',
        'package' => 'package',
        'packages' => 'package',
        'pkg' => 'package',
        'slice' => 'slice',
        'slices' => 'slice',
        'stalk' => 'stalk',
        'stalks' => 'stalk',
        'stick' => 'stick',
        'sticks' => 'stick',
    ];

    /**
     * Unicode fraction map
     */
    private const UNICODE_FRACTIONS = [
        '½' => '1/2',
        '⅓' => '1/3',
        '⅔' => '2/3',
        '¼' => '1/4',
        '¾' => '3/4',
        '⅛' => '1/8',
        '⅜' => '3/8',
        '⅝' => '5/8',
        '⅞' => '7/8',
        '⅕' => '1/5',
        '⅖' => '2/5',
        '⅗' => '3/5',
        '⅘' => '4/5',
        '⅙' => '1/6',
        '⅚' => '5/6',
    ];

    /**
     * Parse an ingredient string like "2 cups all-purpose flour" into components.
     *
     * @return array{quantity: string, unit: string, ingredientName: string, notes: string}
     */
    public function parse(string $ingredient): array
    {
        $ingredient = trim($ingredient);

        // Replace unicode fractions with ASCII
        foreach (self::UNICODE_FRACTIONS as $unicode => $ascii) {
            $ingredient = str_replace($unicode, $ascii, $ingredient);
        }

        // Clean up whitespace
        $ingredient = preg_replace('/\s+/', ' ', $ingredient);

        // Extract parenthetical notes
        $notes = '';
        if (preg_match('/\(([^)]+)\)/', $ingredient, $noteMatch)) {
            $notes = trim($noteMatch[1]);
            $ingredient = trim(str_replace($noteMatch[0], '', $ingredient));
        }

        // Also extract notes after a comma (e.g. "1 cup flour, sifted")
        if (empty($notes) && preg_match('/^(.+?),\s*(.+)$/', $ingredient, $commaMatch)) {
            $ingredient = trim($commaMatch[1]);
            $notes = trim($commaMatch[2]);
        }

        // Pattern: optional quantity, optional unit, then ingredient name
        // Quantity can be: "2", "1/2", "2 1/2", "1-2"
        $quantityPattern = '(?:(\d+(?:\s+\d+\/\d+|\.\d+|\/\d+)?(?:\s*-\s*\d+(?:\/\d+)?)?)\s*)';
        // Build unit alternation from keys, longest first to avoid partial matches
        $unitKeys = array_keys(self::UNIT_MAP);
        usort($unitKeys, function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        $unitAlternation = implode('|', array_map(function ($u) {
            return preg_quote($u, '/');
        }, $unitKeys));
        $unitPattern = '(?:(' . $unitAlternation . ')\.?\s+)';

        // Try: quantity + unit + name
        if (preg_match('/^' . $quantityPattern . $unitPattern . '(.+)$/i', $ingredient, $m)) {
            return [
                'quantity' => $this->normalizeQuantity($m[1]),
                'unit' => $this->mapUnit($m[2]),
                'ingredientName' => trim($m[3]),
                'notes' => $notes,
            ];
        }

        // Try: quantity + name (no unit, e.g. "3 eggs")
        if (preg_match('/^' . $quantityPattern . '(.+)$/i', $ingredient, $m)) {
            return [
                'quantity' => $this->normalizeQuantity($m[1]),
                'unit' => 'none',
                'ingredientName' => trim($m[2]),
                'notes' => $notes,
            ];
        }

        // No quantity found (e.g. "Salt to taste")
        return [
            'quantity' => '',
            'unit' => 'none',
            'ingredientName' => $ingredient,
            'notes' => $notes,
        ];
    }

    /**
     * Map a unit string to its stored dropdown value.
     */
    public function mapUnit(string $unit): string
    {
        $lower = strtolower(trim($unit));
        return self::UNIT_MAP[$lower] ?? 'none';
    }

    /**
     * Normalize quantity string (clean whitespace around fractions).
     */
    private function normalizeQuantity(string $qty): string
    {
        return preg_replace('/\s+/', ' ', trim($qty));
    }
}
