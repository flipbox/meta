<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\migrations;

use craft\db\Migration;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class ContentTable extends Migration
{

    /**
     * @var string|null The table name
     */
    public $tableName;

    /**
     * @inheritdoc
     */
    public function safeUp()
    {

        $this->createTable($this->tableName, [
            'id' => $this->primaryKey(),
            'elementId' => $this->integer()->notNull(),
            'siteId' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(
            $this->db->getIndexName($this->tableName, 'elementId,siteId'),
            $this->tableName,
            'elementId,siteId',
            true
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName($this->tableName, 'elementId'),
            $this->tableName,
            'elementId',
            '{{%elements}}',
            'id',
            'CASCADE',
            null
        );

        $this->addForeignKey(
            $this->db->getForeignKeyName($this->tableName, 'siteId'),
            $this->tableName,
            'siteId',
            '{{%sites}}',
            'id',
            'CASCADE',
            'CASCADE'
        );

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        return false;
    }
}
