<?php

namespace flipbox\meta\migrations;

use craft\db\Migration;
use craft\helpers\MigrationHelper;
use craft\records\Field;
use flipbox\meta\fields\Meta;
use flipbox\meta\records\Meta as MetaRecord;

class m170720_092214_content_rename extends Migration
{

    /**
     * @param $val
     * @return string
     */
    private function getContentTableName($val)
    {
        return '{{%' . $this->getContentTableRef($val) . '}}';
    }

    /**
     * @param $val
     * @return string
     */
    private function getContentTableRef($val)
    {
        return MetaRecord::tableAlias() . 'content_' . $val;
    }

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // find any of the context fields
        $records = Field::find()
            ->andWhere([
                'type' => Meta::class
            ])
            ->all();

        /** @var Field $record */
        foreach ($records as $record) {
            $oldTableName = $this->getContentTableName(strtolower($record->handle));
            $newTableName = $this->getContentTableName($record->id);

            // Remove legacy indexes
            MigrationHelper::dropAllForeignKeysOnTable($oldTableName, $this);
            MigrationHelper::dropAllIndexesOnTable($oldTableName, $this);

            // Rename table
            $this->renameTable($oldTableName, $newTableName);

            // New indexes and foreign keys
            $this->createIndex(
                $this->db->getIndexName($newTableName, 'elementId,siteId'),
                $newTableName,
                'elementId,siteId',
                true
            );

            $this->addForeignKey(
                $this->db->getForeignKeyName($newTableName, 'elementId'),
                $newTableName,
                'elementId',
                '{{%elements}}',
                'id',
                'CASCADE',
                null
            );

            $this->addForeignKey(
                $this->db->getForeignKeyName($newTableName, 'siteId'),
                $newTableName,
                'siteId',
                '{{%sites}}',
                'id',
                'CASCADE',
                'CASCADE'
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m170720_092214_content_rename cannot be reverted.\n";

        return false;
    }
}
