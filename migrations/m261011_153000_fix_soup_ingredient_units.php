<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\elements\Entry;
use MattBloomfield\RecipeHelper\services\NutritionService;
use RuntimeException;

/**
 * Restore missing measurement units on the broccoli cheddar soup and
 * recalculate its nutrition with the corrected ingredient payload.
 */
class m261011_153000_fix_soup_ingredient_units extends Migration
{
    public function safeUp(): bool
    {
        $entry = Entry::find()
            ->section('recipes')
            ->slug('copycat-panera-broccoli-cheddar-soup')
            ->status(null)
            ->one();

        if (!$entry) {
            throw new RuntimeException('Recipe not found: copycat-panera-broccoli-cheddar-soup');
        }

        $unitByIngredient = [
            'unsalted butter' => ['Tbsp', 'C'],
            'all purpose flour' => ['C'],
            'half-and-half' => ['C'],
            'chicken stock' => ['C'],
            'fresh broccoli florets' => ['C'],
            'carrots' => ['C'],
            'nutmeg' => ['tsp'],
            'sharp cheddar cheese' => ['C'],
        ];

        foreach ($entry->getFieldValue('ingredientsAndInstructions')->all() as $block) {
            if ($block->type?->handle !== 'ingredientsBlock') {
                continue;
            }

            $rows = $block->getFieldValue('ingredientsList');
            if (!is_array($rows)) {
                continue;
            }

            $changed = false;
            foreach ($rows as &$row) {
                $name = mb_strtolower(trim($row['ingredientName'] ?? ''));
                if (!isset($unitByIngredient[$name])) {
                    continue;
                }

                $unit = array_shift($unitByIngredient[$name]);
                $row['col2'] = $unit;
                $row['unit'] = $unit;
                $changed = true;
            }
            unset($row);

            if ($changed) {
                $block->setFieldValue('ingredientsList', $rows);
                if (!\Craft::$app->getElements()->saveElement($block)) {
                    $errors = implode(', ', $block->getFirstErrors());
                    throw new RuntimeException("Could not save soup ingredients: $errors");
                }
            }
        }

        $service = new NutritionService();
        $result = $service->calculate($entry);
        if (!$result['success']) {
            throw new RuntimeException("Could not recalculate soup nutrition: {$result['message']}");
        }

        $calories = (float)$entry->getFieldValue('nutritionCalories');
        if ($calories < 100) {
            throw new RuntimeException("Soup nutrition is still implausible: $calories calories per serving");
        }

        if (!\Craft::$app->getElements()->saveElement($entry)) {
            $errors = implode(', ', $entry->getFirstErrors());
            throw new RuntimeException("Could not save soup nutrition: $errors");
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261011_153000_fix_soup_ingredient_units cannot be reverted.\n";
        return false;
    }
}
