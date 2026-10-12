<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\elements\Entry;
use MattBloomfield\RecipeHelper\services\NutritionService;
use RuntimeException;

/**
 * Correct the Bread Bowls serving count and recalculate two bad nutrition rows.
 */
class m261011_150000_recalculate_bread_bowl_and_soup_nutrition extends Migration
{
    public function safeUp(): bool
    {
        $service = new NutritionService();
        $recipes = [
            'homemade-bread-bowls' => 8,
            'copycat-panera-broccoli-cheddar-soup' => null,
        ];

        foreach ($recipes as $slug => $servings) {
            $entry = Entry::find()
                ->section('recipes')
                ->slug($slug)
                ->status(null)
                ->one();

            if (!$entry) {
                throw new RuntimeException("Recipe not found: $slug");
            }

            if ($servings !== null) {
                $entry->setFieldValue('servings', $servings);
            }

            $result = $service->calculate($entry);
            if (!$result['success']) {
                throw new RuntimeException("Could not recalculate $slug: {$result['message']}");
            }

            if (!\Craft::$app->getElements()->saveElement($entry)) {
                $errors = implode(', ', $entry->getFirstErrors());
                throw new RuntimeException("Could not save $slug: $errors");
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261011_150000_recalculate_bread_bowl_and_soup_nutrition cannot be reverted.\n";
        return false;
    }
}
