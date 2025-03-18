<?php

namespace craft\contentmigrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

/**
 * m250318_151741_fix_measurement_units migration.
 */
class m250318_151741_fix_measurement_units extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {

        $rows = (new Query())
            ->select(['id', 'content'])
            ->from('elements_sites')
            ->where(['like', 'content', '8f632f02-810b-4c80-8521-fd9f11830cf9'])
            ->all();

        foreach ($rows as $row) {
            $content = Json::decode($row['content'], true);
            foreach ($content['8f632f02-810b-4c80-8521-fd9f11830cf9'] as &$ingredient) {
                if ($ingredient['col2'] === 'c') {
                    $ingredient['col2'] = 'C';
                }
                if ($ingredient['col2'] === 'flOz') {
                    $ingredient['col2'] = 'fl oz';
                }
                if ($ingredient['col2'] === 'pound') {
                    $ingredient['col2'] = 'lbs';
                }
            }

            $this->update('elements_sites', ['content' => $content], ['id' => $row['id']]);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m250318_151741_fix_measurement_units cannot be reverted.\n";
        return false;
    }
}
