<?php

use yii\db\Migration;
use yii\db\Schema;

class m180125_035041_add_comment_dingding_token extends Migration
{
    public function up()
    {
        $this->addColumn(\app\models\Project::tableName(), 'ding_token', Schema::TYPE_TEXT . ' COMMENT "ding ding robot token"');
        $this->addColumn(\app\models\Task::tableName(), 'remark', Schema::TYPE_TEXT . ' COMMENT "task remark"');
    }

    public function down()
    {
//        echo "m180125_035041_add_comment_dingding_token cannot be reverted.\n";
        $this->dropColumn(\app\models\Project::tableName(), 'ding_token');
        $this->dropColumn(\app\models\Task::tableName(), 'remark');
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
