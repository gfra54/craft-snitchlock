<?php
namespace gfra54\snitchlock\migrations;

use craft\db\Migration;

class Install extends Migration
{
    public function safeUp()
    {
        // create our table
        $this->createTable('{{%snitchlock_collisions}}', [
            'id' => $this->primaryKey(),
            'snitchlockId' => $this->integer()->notNull(),
            'snitchlockType' => $this->string(),
            'userId' => $this->integer()->notNull(),
            'whenEntered' => $this->dateTime()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // foreign keys: our userId must be a user id
        $this->addForeignKey(
            $this->db->getForeignKeyName('{{%snitchlock_collisions}}', 'userId'),
            '{{%snitchlock_collisions}}', 'userId', '{{%users}}', 'id', 'CASCADE', null);
    }

    public function safeDown()
    {
        // remove the table on uninstall
        $this->dropTableIfExists('{{%snitchlock_collisions}}');
    }
}