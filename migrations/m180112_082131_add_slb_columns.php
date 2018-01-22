<?php

use app\models\Project;
use yii\db\Migration;
use yii\db\Schema;

class m180112_082131_add_slb_columns extends Migration
{
    public function up()
    {
        $this->addColumn(Project::tableName(), 'slb_status', Schema::TYPE_SMALLINT . '(1) NOT NULL DEFAULT 0');
        $this->addColumn(Project::tableName(), 'slb_config', Schema::TYPE_TEXT);
        $this->addColumn(Project::tableName(), 'slb_type', Schema::TYPE_STRING . '(50) DEFAULT "aliyun"');
        $this->addColumn(Project::tableName(), 'test_url', Schema::TYPE_TEXT);
    }

    public function down()
    {
        $this->dropColumn(Project::tableName(), 'slb_status');
        $this->dropColumn(Project::tableName(), 'slb_config');
        $this->dropColumn(Project::tableName(), 'slb_type');
        $this->dropColumn(Project::tableName(), 'test_url');
        return true;
    }

    /*
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}
