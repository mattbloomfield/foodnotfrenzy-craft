<?php

namespace craft\contentmigrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;
use MattBloomfield\RecipeHelper\quantity\QuantityNormalizer;

/**
 * Normalize Unicode fraction glyphs in existing ingredient quantities.
 */
class m261011_120000_normalize_unicode_ingredient_quantities extends Migration
{
    private const INGREDIENTS_LAYOUT_ELEMENT_UID = '8f632f02-810b-4c80-8521-fd9f11830cf9';

    public function safeUp(): bool
    {
        $rows = (new Query())
            ->select(['id', 'content'])
            ->from('{{%elements_sites}}')
            ->where(['like', 'content', self::INGREDIENTS_LAYOUT_ELEMENT_UID])
            ->all();

        foreach ($rows as $row) {
            $content = Json::decode($row['content'], true);
            $ingredients = $content[self::INGREDIENTS_LAYOUT_ELEMENT_UID] ?? null;
            if (!is_array($ingredients)) {
                continue;
            }

            $changed = false;
            foreach ($ingredients as &$ingredient) {
                if (!is_array($ingredient)) {
                    continue;
                }

                $quantity = QuantityNormalizer::normalize($ingredient['col1'] ?? '');
                if ($quantity !== ($ingredient['col1'] ?? '')) {
                    $ingredient['col1'] = $quantity;
                    $changed = true;
                }
            }
            unset($ingredient);

            if ($changed) {
                $content[self::INGREDIENTS_LAYOUT_ELEMENT_UID] = $ingredients;
                $this->update('{{%elements_sites}}', ['content' => $content], ['id' => $row['id']]);
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261011_120000_normalize_unicode_ingredient_quantities cannot be reverted.\n";
        return false;
    }
}
