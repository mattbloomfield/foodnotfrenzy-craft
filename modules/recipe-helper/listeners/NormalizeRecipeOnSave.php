<?php

namespace MattBloomfield\RecipeHelper\listeners;

use CraftCms\Cms\Element\Events\ElementLifecycleSaving;
use CraftCms\Cms\Entry\Elements\Entry;
use Illuminate\Support\Facades\Log;
use MattBloomfield\RecipeHelper\quantity\QuantityNormalizer;
use MattBloomfield\RecipeHelper\services\NutritionService;

/**
 * Before an entry is saved:
 * - ingredient blocks get their table quantities normalized;
 * - canonical recipe saves recalculate nutrition when the ingredients changed,
 *   or when the editor asked for it with the `forceNutrition` sidebar control.
 */
class NormalizeRecipeOnSave
{
    public function handle(ElementLifecycleSaving $event): void
    {
        $entry = $event->element;

        if (!$entry instanceof Entry) {
            return;
        }

        $typeHandle = $entry->getType()->handle;

        // Matrix blocks are entries. Normalize their table rows immediately
        // before persistence, regardless of whether they are being saved via a
        // recipe or on their own.
        if ($typeHandle === 'ingredientsBlock') {
            self::normalizeIngredientBlock($entry);
            return;
        }

        // Only recipe entries, only canonical saves (not drafts/autosaves)
        if ($typeHandle !== 'recipe' || $entry->getIsDraft() || $entry->propagating || $entry->resaving) {
            return;
        }

        // Normalize loaded blocks before nutrition reads them. The block-level
        // hook above is the final persistence backstop.
        foreach ($entry->getFieldValue('ingredientsAndInstructions')->all() as $block) {
            if ($block->getType()->handle === 'ingredientsBlock') {
                self::normalizeIngredientBlock($block);
            }
        }

        $service = new NutritionService();

        // Check for force flag (set from the edit screen's sidebar)
        $force = !app()->runningInConsole()
            && !request()->isMethodSafe()
            && filter_var(request()->input('forceNutrition'), FILTER_VALIDATE_BOOLEAN);

        if ($force || $service->needsRecalculation($entry)) {
            $result = $service->calculate($entry);
            if (!$result['success']) {
                Log::warning("Nutrition calculation failed: {$result['message']}");
            }
        }
    }

    private static function normalizeIngredientBlock(Entry $block): void
    {
        $rows = $block->getFieldValue('ingredientsList');
        if (!is_array($rows)) {
            return;
        }

        $normalizedRows = $rows;
        $changed = false;

        foreach ($normalizedRows as &$row) {
            if (!is_array($row)) {
                continue;
            }

            $storedQuantity = $row['quantity'] ?? $row['col1'] ?? '';
            $quantity = QuantityNormalizer::normalize($storedQuantity);
            if ($quantity !== $storedQuantity) {
                // Table values expose both the column ID and handle. Keep both
                // in sync because database serialization reads the column ID.
                $row['col1'] = $quantity;
                $row['quantity'] = $quantity;
                $changed = true;
            }
        }
        unset($row);

        if ($changed) {
            $block->setFieldValue('ingredientsList', $normalizedRows);
        }
    }
}
