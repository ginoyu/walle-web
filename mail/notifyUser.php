<?php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var \app\models\User $user
 * @var \app\models\Task $task
 * @var integer $notify_admin
 */

$confirmationLink = Yii::$app->urlManager->createAbsoluteUrl(['task/index']);
?>
<?= yii::t('user', 'dear') ?><strong><?= $user->realname ?></strong>:

<br><br>
<?php if (!$notify_admin) { ?>
    <span style="text-indent: 2em"><?= yii::t('task', 'task tips') ?><?= $task->title ?>
        &nbsp;&nbsp;&nbsp;<?= yii::t('task', 'task_notify_' . $task->status) ?></span>
<?php } else { ?>
    <span style="text-indent: 2em"><?= yii::t('task', 'task tips') . $task->title ?>&nbsp;&nbsp;&nbsp;
        <?= yii::t('task', 'task audit tips') ?></span>
<?php } ?>

<br><br>
<h3><?= Html::a(yii::t('task', 'check out'), $confirmationLink) ?></h3>
