<?php
/**
 * Tests for recipe quantity scaling and unit conversion.
 * No dependencies — run with: php tests/quantity-test.php (or ddev php ...)
 */

require __DIR__ . '/../modules/recipe-helper/quantity/Rational.php';
require __DIR__ . '/../modules/recipe-helper/quantity/UnitRegistry.php';
require __DIR__ . '/../modules/recipe-helper/quantity/QuantityFormatter.php';

use MattBloomfield\RecipeHelper\quantity\QuantityFormatter;

$converter = new QuantityFormatter();
$failures = 0;
$passes = 0;

function check(string $label, mixed $actual, mixed $expected): void
{
    global $failures, $passes;
    if ($actual === $expected) {
        $passes++;
        return;
    }
    $failures++;
    echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

// --- Basic scaling and fraction display ---
check('whole number, no unit', $converter->format('2', 1), '2');
check('doubling a mixed number', $converter->format('1 1/2', 2), '3');
check('1/3 x 3 is exactly 1', $converter->format('1/3', 3), '1');
check('1/3 x 2 renders glyph', $converter->format('1/3', 2), '⅔');
check('decimal input', $converter->format('1.5', 2), '3');
check('mixed number glyph', $converter->format('3/4', 3), '2 ¼');

// --- Empty / zero quantities ---
check('empty quantity, no unit', $converter->format('', 1), '');
check('null quantity, no unit', $converter->format(null, 1), '');
check('zero quantity, no unit', $converter->format(0, 1), '');
check('empty quantity with unit names it', $converter->format('', 1, 'pn'), ['value' => '', 'unit' => 'pinch']);

// --- Unit display and pluralization ---
check('1 cup singular', $converter->format('1', 1, 'C'), ['value' => '1', 'unit' => 'cup']);
check('2 cups plural', $converter->format('1', 2, 'C'), ['value' => '2', 'unit' => 'cups']);
check('half cup is singular', $converter->format('1/2', 1, 'C'), ['value' => '½', 'unit' => 'cup']);
check('fl oz invariant plural', $converter->format('4', 1, 'fl oz'), ['value' => '4', 'unit' => 'fl oz']);
check('pinch pluralizes properly', $converter->format('2', 1, 'pn'), ['value' => '2', 'unit' => 'pinches']);
check('box pluralizes properly', $converter->format('2', 1, 'box'), ['value' => '2', 'unit' => 'boxes']);
check('inch pluralizes properly', $converter->format('3', 1, 'inch'), ['value' => '3', 'unit' => 'inches']);
check('gallon spelled out', $converter->format('2', 1, 'gal'), ['value' => '2', 'unit' => 'gallons']);
check('large is invariant', $converter->format('2', 1, 'large'), ['value' => '2', 'unit' => 'large']);
check('unknown unit passes through un-pluralized', $converter->format('2', 1, 'sprig'), ['value' => '2', 'unit' => 'sprig']);

// --- Unit promotion ---
check('3 tsp promotes to 1 tablespoon (singular)', $converter->format('1', 3, 'tsp'), ['value' => '1', 'unit' => 'tablespoon']);
check('4 Tbsp promotes to quarter cup (singular)', $converter->format('2', 2, 'Tbsp'), ['value' => '¼', 'unit' => 'cup']);
check('48 tsp promotes through to 1 cup', $converter->format('12', 4, 'tsp'), ['value' => '1', 'unit' => 'cup']);
check('5 tsp promotes to mixed tablespoons', $converter->format('5', 1, 'tsp'), ['value' => '1 ⅔', 'unit' => 'tablespoons']);
check('32 oz promotes to 2 pounds', $converter->format('16', 2, 'oz'), ['value' => '2', 'unit' => 'pounds']);
check('17 oz stays in ounces (no ugly fraction)', $converter->format('17', 1, 'oz'), ['value' => '17', 'unit' => 'ounces']);
check('5 Tbsp stays in tablespoons (5/16 cup not clean)', $converter->format('5', 1, 'Tbsp'), ['value' => '5', 'unit' => 'tablespoons']);

// --- Unit demotion ---
check('half Tbsp demotes to teaspoons', $converter->format('1/2', 1, 'Tbsp'), ['value' => '1 ½', 'unit' => 'teaspoons']);
check('eighth cup demotes to 2 tablespoons', $converter->format('1/8', 1, 'C'), ['value' => '2', 'unit' => 'tablespoons']);
check('half pound demotes to 8 ounces', $converter->format('1/2', 1, 'lbs'), ['value' => '8', 'unit' => 'ounces']);
check('third Tbsp demotes to 1 teaspoon (singular)', $converter->format('1/3', 1, 'Tbsp'), ['value' => '1', 'unit' => 'teaspoon']);

// --- Countable units never convert ---
check('cloves scale without conversion', $converter->format('2', 4, 'clove'), ['value' => '8', 'unit' => 'cloves']);
check('half can stays a can', $converter->format('1/2', 1, 'can'), ['value' => '½', 'unit' => 'can']);

// --- Ranges ---
check('range scales both ends', $converter->format('1-2', 2, 'C'), ['value' => '2-4', 'unit' => 'cups']);
check('range with fractions', $converter->format('1/2-1', 2, 'C'), ['value' => '1-2', 'unit' => 'cups']);
check('range without unit', $converter->format('1-2', 3), '3-6');
check('range keeps its unit (no promotion)', $converter->format('4-6', 1, 'Tbsp'), ['value' => '4-6', 'unit' => 'tablespoons']);

// --- Unparseable input passes through ---
check('text quantity passes through', $converter->format('a few', 2, 'C'), ['value' => 'a few', 'unit' => 'cups']);
check('text quantity without unit', $converter->format('to taste', 2), 'to taste');

// --- formatMinutes ---
check('0 minutes', $converter->formatMinutes(0), '0 min');
check('45 minutes', $converter->formatMinutes(45), '45 min');
check('90 minutes', $converter->formatMinutes(90), '1 hour 30 min');
check('120 minutes', $converter->formatMinutes(120), '2 hours');

echo $failures === 0
    ? "OK: $passes tests passed\n"
    : "\n$failures FAILED, $passes passed\n";
exit($failures === 0 ? 0 : 1);
