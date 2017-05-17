<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\migrations;

use Craft;
use craft\db\Migration as InstallMigration;
use craft\db\Query;
use craft\records\Element as ElementRecord;
use craft\records\Field as FieldRecord;
use craft\records\Site as SiteRecord;
use flipbox\meta\fields\Meta;
use flipbox\meta\records\Meta as MetaRecord;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Install extends InstallMigration
{

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {

        $fieldIds = (new Query())
            ->select([
                'id'
            ])
            ->from(['{{%fields}} fields'])
            ->andWhere([
                'fields.type' => Meta::class,
                'fields.context' => 'global'
            ])->column();

        foreach ($fieldIds as $fieldId) {
            Craft::$app->getFields()->deleteFieldById($fieldId);
        }

        // Delete tables
        $this->dropTableIfExists(MetaRecord::tableName());

        return true;

    }

    /**
     * Creates the tables.
     *
     * @return void
     */
    protected function createTables()
    {

        $this->createTable(MetaRecord::tableName(), [
            'id' => $this->integer()->notNull(),
            'ownerId' => $this->integer()->notNull(),
            'ownerSiteId' => $this->integer(),
            'fieldId' => $this->integer()->notNull(),
            'sortOrder' => $this->smallInteger()->unsigned(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
            'PRIMARY KEY(id)',
        ]);

    }

    /**
     * Creates the indexes.
     *
     * @return void
     */
    protected function createIndexes()
    {

        $this->createIndex(
            $this->db->getIndexName(MetaRecord::tableName(), 'ownerId', false, true),
            MetaRecord::tableName(),
            'ownerId',
            false
        );

        $this->createIndex(
            $this->db->getIndexName(MetaRecord::tableName(), 'ownerSiteId', false, true),
            MetaRecord::tableName(),
            'ownerSiteId',
            false
        );

        $this->createIndex(
            $this->db->getIndexName(MetaRecord::tableName(), 'fieldId', false, true),
            MetaRecord::tableName(),
            'fieldId',
            false
        );

    }

    /**
     * Adds the foreign keys.
     *
     * @return void
     */
    protected function addForeignKeys()
    {

        $this->addForeignKey(
            $this->db->getForeignKeyName(MetaRecord::tableName(), 'id'),
            MetaRecord::tableName(), 'id', ElementRecord::tableName(), 'id', 'CASCADE', null
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName(MetaRecord::tableName(), 'ownerId'),
            MetaRecord::tableName(), 'ownerId', ElementRecord::tableName(), 'id', 'CASCADE', null
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName(MetaRecord::tableName(), 'ownerSiteId'),
            MetaRecord::tableName(), 'ownerSiteId', SiteRecord::tableName(), 'id', 'CASCADE', null
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName(MetaRecord::tableName(), 'fieldId'),
            MetaRecord::tableName(), 'fieldId', FieldRecord::tableName(), 'id', 'CASCADE', null
        );

    }

}
