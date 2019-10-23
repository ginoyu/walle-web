<?php

use app\models\Project;
use yii\db\Migration;
use yii\db\Schema;

class m191021_082131_add_commit_id extends Migration
{
    public function up()
    {
        $this->addColumn(Project::tableName(), 'commit_id', Schema::TYPE_STRING . '(50)');
    }

    public function down()
    {
        $this->dropColumn(Project::tableName(), 'commit_id');
        return true;
    }
}
