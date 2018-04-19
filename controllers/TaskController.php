<?php

namespace app\controllers;

use app\components\AlertUtils;
use app\components\Command;
use app\components\PermissionHelper;
use app\models\User;
use yii;
use yii\data\Pagination;
use yii\helpers\Url;
use app\components\Controller;
use app\models\Task;
use app\models\Project;
use app\models\Group;

class TaskController extends Controller
{

    protected $task;

    public function actionChangeFlow() {
        $machine = $this->getParam('machine');
        $flow = $this->getParam('flow');
        if (is_numeric($flow)) $flow = intval($flow);
        $slbClient = new AliyunSlb();
        $r = $slbClient->changeSlb($machine, $flow);
        $this->renderJson([$r], self::SUCCESS);
    }

    /**
     * 我的上线列表
     *
     * @param int $page
     * @param int $size
     * @return string
     */
    public function actionIndex($page = 1, $size = 15)
    {
        $size = $this->getParam('per-page') ?: $size;
        $list = Task::find()
                    ->with('user')
                    ->with('project')
                    ->where(['user_id' => $this->uid]);

        $projectTable = Project::tableName();
        $groupTable = Group::tableName();
        $projects = Project::find()
                           ->leftJoin(Group::tableName(), "`$groupTable`.`project_id` = `$projectTable`.`id`")
                           ->where([
                               "`$projectTable`.status" => Project::STATUS_VALID,
                               "`$groupTable`.`user_id`" => $this->uid
                           ])
                           ->asArray()
                           ->all();

        // 有审核权限的任务
        $auditProjects = Group::getAuditProjectIds($this->uid);
        if ($auditProjects) {
            $list->orWhere(['project_id' => $auditProjects]);
        }

        $permissionArray = Group::getUserProject($this->uid);
//        print_r(Group::getUserProject($this->uid));

        $kw = \Yii::$app->request->post('kw');
        if ($kw) {
            $list->andWhere(['or', "commit_id like '%" . $kw . "%'", "title like '%" . $kw . "%'"]);
        }

        $projectId = (int)\Yii::$app->request->post('project_id');
        if (!empty($projectId)) {
            $list->andWhere(['=', 'project_id', $projectId]);
        }

        $tasks = $list->orderBy('id desc');
        $list = $tasks->offset(($page - 1) * $size)
                      ->limit($size)
                      ->asArray()
                      ->all();

        $pages = new Pagination(['totalCount' => $tasks->count(), 'pageSize' => $size]);

        return $this->render('list', [
            'list' => $list,
            'pages' => $pages,
            'audit' => $auditProjects,
            'projects' => $projects,
            'kw' => $kw,
            'projectId' => $projectId,
            'permission' => $permissionArray
        ]);
    }

    /**
     * 提交任务
     *
     * @param integer $projectId 没有projectId则显示列表
     * @return string
     * @throws
     */
    public function actionSubmit($projectId = null)
    {

        // 为了方便用户更改表名，避免表名直接定死
        $projectTable = Project::tableName();
        $groupTable = Group::tableName();
        if (!$projectId) {
            // 显示所有项目列表
            $projects = Project::find()
                               ->leftJoin(Group::tableName(), "`$groupTable`.`project_id` = `$projectTable`.`id`")
                               ->where([
                                   "`$projectTable`.status" => Project::STATUS_VALID,
                                   "`$groupTable`.`user_id`" => $this->uid
                               ])
                               ->asArray()
                               ->all();

            return $this->render('select-project', [
                'projects' => $projects,
            ]);
        }

        $task = new Task();

        $conf = Project::getConf($projectId);
        if (!$conf) {
            throw new \Exception(yii::t('task', 'unknown project'));
        }

        if (\Yii::$app->request->getIsPost()) {

            $group = Group::find()
                          ->where(['user_id' => $this->uid, 'project_id' => $projectId])
                          ->count();
            if (!$group) {
                throw new \Exception(yii::t('task', 'you are not the member of project'));
            }

            if ($task->load(\Yii::$app->request->post())) {
                // 是否需要审核
                $status = $conf->audit == Project::AUDIT_YES ? Task::STATUS_SUBMIT : Task::STATUS_PASS;
                $task->user_id = $this->uid;
                $task->project_id = $projectId;
                $task->status = $status;
                if ($task->save()) {
                    if ($conf->audit == Project::AUDIT_YES) {
                        $adminIds = Group::getAdminTypeIds($task->project_id);
                        if (isset($adminIds) && is_array($adminIds) && count($adminIds) > 0) {
                            $user = User::findIdentity($adminIds[0]);
                            Yii::$app->mail->compose('notifyUser', ['user' => $user, 'task' => $task, 'notify_admin' => 1])
                                ->setFrom(Yii::$app->mail->messageConfig['from'])
                                ->setTo($user->email)
                                ->setSubject('roobo - ' . $user->realname)
                                ->queue();
                        }
                    }

                    return $this->redirect('@web/task/');
                }
            }
        }

        $tpl = $conf->repo_type == Project::REPO_GIT ? 'submit-git' : 'submit-svn';

        return $this->render($tpl, [
            'task' => $task,
            'conf' => $conf,
        ]);
    }

    /**
     * 任务删除
     *
     * @return string
     * @throws \Exception
     */
    public function actionDelete($taskId)
    {
        $task = Task::findOne($taskId);
        if (!$task) {
            throw new \Exception(yii::t('task', 'unknown deployment bill'));
        }
        if ($task->user_id != $this->uid) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }
        if ($task->status == Task::STATUS_DONE) {
            throw new \Exception(yii::t('task', 'can\'t delele the job which is done'));
        }
        if (!$task->delete()) {
            throw new \Exception(yii::t('w', 'delete failed'));
        }
        $this->renderJson([]);
    }

    /**
     * 生成回滚任务
     *
     * @return string
     * @throws \Exception
     */
    public function actionRollback($taskId)
    {
        $this->task = Task::findOne($taskId);
        if (!$this->task) {
            throw new \Exception(yii::t('task', 'unknown deployment bill'));
        }
        if ($this->task->user_id != $this->uid) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }
        if ($this->task->ex_link_id == $this->task->link_id) {
            throw new \Exception(yii::t('task', 'no rollback twice'));
        }
        $conf = Project::find()
                       ->where(['id' => $this->task->project_id, 'status' => Project::STATUS_VALID])
                       ->one();
        if (!$conf) {
            throw new \Exception(yii::t('task', 'can\'t rollback the closed project\'s job'));
        }

        // 是否需要审核
        $status = $conf->audit == Project::AUDIT_YES ? Task::STATUS_SUBMIT : Task::STATUS_PASS;

        $rollbackTask = new Task();
        $rollbackTask->attributes = $this->task->attributes;
        $rollbackTask->commit_id = $this->task->getRollbackCommitId();
        $rollbackTask->status = $status;
        $rollbackTask->action = Task::ACTION_ROLLBACK;
        $rollbackTask->link_id = $this->task->ex_link_id;
        $rollbackTask->title = $this->task->title . ' - ' . yii::t('task', 'rollback');
        if ($rollbackTask->save()) {
            $url = $conf->audit == Project::AUDIT_YES ? Url::to('@web/task/') : Url::to('@web/walle/deploy?taskId=' . $rollbackTask->id);
            $this->renderJson([
                'url' => $url,
            ]);
        } else {
            $this->renderJson([], -1, yii::t('task', 'create a rollback job failed'));
        }
    }

    /**
     * 任务审核
     *
     * @param $id
     * @param $operation
     */
    public function actionTaskOperation($id, $operation, $user_type)
    {
        $task = Task::findOne($id);
        if (!$task) {
            static::renderJson([], -1, yii::t('task', 'unknown deployment bill'));
        }

        $db_usr_type = Group::getUserProject($this->uid)[$task->project_id];

        // 是否为该项目的审核管理员（超级管理员可以不用审核，如果想审核就得设置为审核管理员，要不只能维护配置）
        if (!($db_usr_type & $user_type)) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }

        $status = PermissionHelper::getStatus($user_type, $operation, $task->status);
        if ($status == -1) {
            throw new \Exception(yii::t('w', 'permission error'));
        }

        $task->status = $status;
        $task->save();


        $this->sendEmail($task);

        static::renderJson(['status' => \Yii::t('w', 'task_status_' . $task->status)]);
    }

    public function sendEmail($task)
    {
        try {
            // email notify task owner
            if ($task->status == Task::STATUS_PASS ||
                $task->status == Task::STATUS_REFUSE ||
                $task->status == Task::STATUS_TEST_LEADER_FAILED ||
                $task->status == Task::STATUS_OPS_LEADER_FAILED) {
                $user = User::findIdentity($task->user_id);
                Yii::$app->mail->compose('notifyUser', ['user' => $user, 'task' => $task, 'notify_admin' => 0])
                    ->setFrom(Yii::$app->mail->messageConfig['from'])
                    ->setTo($user->email)
                    ->setSubject(AlertUtils::getAuditSubject($task))
                    ->queue();
            } else {
                $adminType = Group::TYPE_TESTER;
                if ($task->status == Task::STATUS_TEC_LEADER_PASS) {
                    $adminType = Group::TYPE_TESTER;
                } else if ($task->status == Task::STATUS_TEST_LEADER_PASS) {
                    $adminType = Group::TYPE_OPERATIONS;
                }
                $adminIds = Group::getAdminTypeIds($task->project_id, $adminType);
                if (isset($adminIds) && is_array($adminIds) && count($adminIds) > 0) {
                    $user = User::findIdentity($adminIds[0]);
                    Yii::$app->mail->compose('notifyUser', ['user' => $user, 'task' => $task, 'notify_admin' => 1])
                        ->setFrom(Yii::$app->mail->messageConfig['from'])
                        ->setTo($user->email)
                        ->setSubject(AlertUtils::getAuditSubject($task))
                        ->queue();
                }

            }
        } catch (\Exception $e) {
            Command::log('exception happend!' . $e->getTraceAsString());
            throw $e;
        }

    }

}
