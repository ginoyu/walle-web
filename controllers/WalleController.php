<?php
/* *****************************************************************
 * @Author: wushuiyong
 * @Created Time : 一  9/21 13:48:30 2015
 *
 * @File Name: WalleController.php
 * @Description:
 * *****************************************************************/

namespace app\controllers;

use app\components\Ansible;
use app\components\Command;
use app\components\Controller;
use app\components\DingDingBot;
use app\components\Folder;
use app\components\GlobalHelper;
use app\components\ISlb;
use app\components\Repo;
use app\components\SlbFactory;
use app\components\Task as WalleTask;
use app\components\TaskStateManager;
use app\models\Project;
use app\models\Group;
use app\models\Record;
use app\models\Task as TaskModel;
use yii;

class WalleController extends Controller
{

    /**
     * 项目配置
     */
    protected $conf;

    /**
     * 上线任务配置
     */
    protected $task;

    /**
     * Walle的高级任务
     */
    protected $walleTask;

    /**
     * Ansible 任务
     */
    protected $ansible;

    /**
     * Walle的文件目录操作
     */
    protected $walleFolder;

    public $enableCsrfValidation = false;

    /**
     * 发起上线
     *
     * @throws \Exception
     */
    public function actionStartDeploy()
    {
        $taskId = \Yii::$app->request->post('taskId');
        if (!$taskId) {
            $this->renderJson([], -1, yii::t('walle', 'deployment id is empty'));
        }
        $this->task = TaskModel::findOne($taskId);
        if (!$this->task) {
            throw new \Exception(yii::t('walle', 'deployment id not exists'));
        }
        if ($this->task->user_id != $this->uid) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }
        // 任务失败或者审核通过时可发起上线
        if (!in_array($this->task->status, [TaskModel::STATUS_PASS, TaskModel::STATUS_FAILED])) {
            throw new \Exception(yii::t('walle', 'deployment only done for once'));
        }
        // 清除历史记录
        Record::deleteAll(['task_id' => $this->task->id]);

        $taskStateManager = new TaskStateManager();
        $taskStateManager->setRunningTask($taskId);


        // 项目配置
        $this->conf = Project::getConf($this->task->project_id);
        $this->walleTask = new WalleTask($this->conf);
        $this->walleFolder = new Folder($this->conf);
        try {
            if ($this->task->action == TaskModel::ACTION_ONLINE) {
                $this->_makeVersion();
                $this->_initWorkspace();
                $this->_preDeploy();
                $this->_revisionUpdate();
                $this->_postDeploy();
                $this->_transmission();
                $this->_updateRemoteServers($this->task->link_id, $this->conf->post_release_delay);
                $this->_cleanRemoteReleaseVersion();
                $this->_cleanUpLocal($this->task->link_id);
            } else {
                $this->_rollback($this->task->ex_link_id);
            }

            /** 至此已经发布版本到线上了，需要做一些记录工作 */

            // 记录此次上线的版本（软链号）和上线之前的版本
            ///对于回滚的任务不记录线上版本
            if ($this->task->action == TaskModel::ACTION_ONLINE) {
                $this->task->ex_link_id = $this->conf->version;
            }
            // 第一次上线的任务不能回滚、回滚的任务不能再回滚
            if ($this->task->action == TaskModel::ACTION_ROLLBACK || $this->task->id == 1) {
                $this->task->enable_rollback = TaskModel::ROLLBACK_FALSE;
            }
            $this->task->status = TaskModel::STATUS_DONE;
            $this->task->save();

            // 可回滚的版本设置
            $this->_enableRollBack();

            // 记录当前线上版本（软链）回滚则是回滚的版本，上线为新版本
            $this->conf->version = $this->task->link_id;
            $this->conf->save();

            $dingding = new DingDingBot($this->conf->ding_token);
            $dingding->sendToAll('上线单:'.$this->task->title.'  '.'上线成功');

        } catch (\Exception $e) {
            Command::log('exception happend!' . $e->getTraceAsString());
            $this->task->status = TaskModel::STATUS_FAILED;
            $this->task->save();
            // 清理本地部署空间
            $this->_cleanUpLocal($this->task->link_id);

            $taskStateManager->clearRunningTaskState($taskId);

            $dingding = new DingDingBot($this->conf->ding_token);
            $dingding->sendToAll('上线单:'.$this->task->title.'  '.'上线失败');
            throw $e;
        }
        $taskStateManager->clearRunningTaskState($taskId);
        $this->renderJson([]);
    }

    /**
     * 提交任务
     *
     * @return string
     */
    public function actionCheck()
    {
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

        return $this->render('check', [
            'projects' => $projects,
        ]);
    }

    /**
     * 项目配置检测，提前发现配置不当之处。
     *
     * @return string
     */
    public function actionDetection($projectId)
    {
        $project = Project::getConf($projectId);
        $log = [];
        $code = 0;

        // 本地git ssh-key是否加入deploy-keys列表
        $revision = Repo::getRevision($project);
        try {

            // 1.检测宿主机检出目录是否可读写
            $codeBaseDir = Project::getDeployFromDir();
            $isWritable = is_dir($codeBaseDir) ? is_writable($codeBaseDir) : @mkdir($codeBaseDir, 0755, true);
            if (!$isWritable) {
                $code = -1;
                $log[] = yii::t('walle', 'hosted server is not writable error', [
                    'user' => getenv("USER"),
                    'path' => $project->deploy_from,
                ]);
            }

            // 2.检测宿主机ssh是否加入git信任
            $ret = $revision->updateRepo();
            if (!$ret) {
                $code = -1;
                $error = $project->repo_type == Project::REPO_GIT ? yii::t('walle', 'ssh-key to git',
                    ['user' => getenv("USER")]) : yii::t('walle', 'correct username passwd');
                $log[] = yii::t('walle', 'hosted server ssh error', [
                    'error' => $error,
                ]);
            }

            if ($project->ansible) {
                $this->ansible = new Ansible($project);

                // 3.检测 ansible 是否安装
                $ret = $this->ansible->test();
                if (!$ret) {
                    $code = -1;
                    $log[] = yii::t('walle', 'hosted server ansible error');
                }
            }
        } catch (\Exception $e) {
            $code = -1;
            $log[] = yii::t('walle', 'hosted server sys error', [
                'error' => $e->getMessage()
            ]);
        }

        // 权限与免密码登录检测
        $this->walleTask = new WalleTask($project);
        try {
            // 4.检测php用户是否加入目标机ssh信任
            $command = 'id';
            $ret = $this->walleTask->runRemoteTaskCommandPackage([$command]);
            if (!$ret) {
                $code = -1;
                $log[] = yii::t('walle', 'target server ssh error', [
                    'local_user' => getenv("USER"),
                    'remote_user' => $project->release_user,
                    'path' => $project->release_to,
                ]);
            }

            if ($project->ansible) {
                // 5.检测 ansible 连接目标机是否正常
                $ret = $this->ansible->ping();
                if (!$ret) {
                    $code = -1;
                    $log[] = yii::t('walle', 'target server ansible ping error');
                }
            }

            // 6.检测php用户是否具有目标机release目录读写权限
            $tmpDir = 'detection' . time();
            $command = sprintf('mkdir -p %s', Project::getReleaseVersionDir($tmpDir));
            $ret = $this->walleTask->runRemoteTaskCommandPackage([$command]);
            if (!$ret) {
                $code = -1;
                $log[] = yii::t('walle', 'target server is not writable error', [
                    'remote_user' => $project->release_user,
                    'path' => $project->release_library,
                ]);
            }

            // 清除
            $command = sprintf('rm -rf %s', Project::getReleaseVersionDir($tmpDir));
            $this->walleTask->runRemoteTaskCommandPackage([$command]);
        } catch (\Exception $e) {
            $code = -1;
            $log[] = yii::t('walle', 'target server sys error', [
                'error' => $e->getMessage()
            ]);
        }

        // 7.路径必须为绝对路径
        $needAbsoluteDir = [
            Yii::t('conf', 'deploy from') => Project::getConf()->deploy_from,
            Yii::t('conf', 'webroot') => Project::getConf()->release_to,
            Yii::t('conf', 'releases') => Project::getConf()->release_library,
        ];
        foreach ($needAbsoluteDir as $tips => $dir) {
            if (0 !== strpos($dir, '/')) {
                $code = -1;
                $log[] = yii::t('walle', 'config dir must absolute', [
                    'path' => sprintf('%s:%s', $tips, $dir),
                ]);
            }
        }

        // task 检测todo...

        if ($code === 0) {
            $log[] = yii::t('walle', 'project configuration works');
        }
        $this->renderJson(join("<br>", $log), $code);
    }

    /**
     * 获取线上文件md5
     *
     * @param $projectId
     */
    public function actionFileMd5($projectId, $file)
    {
        // 配置
        $this->conf = Project::getConf($projectId);

        $this->walleFolder = new Folder($this->conf);
        $projectDir = $this->conf->release_to;
        $file = sprintf("%s/%s", rtrim($projectDir, '/'), $file);

        $this->walleFolder->getFileMd5($file);
        $log = $this->walleFolder->getExeLog();

        $this->renderJson(nl2br($log));
    }

    /**
     * 获取branch分支列表
     *
     * @param $projectId
     */
    public function actionGetBranch($projectId)
    {
        $conf = Project::getConf($projectId);

        $version = Repo::getRevision($conf);
        $list = $version->getBranchList();

        $this->renderJson($list);
    }

    /**
     * 获取commit历史
     *
     * @param $projectId
     */
    public function actionGetCommitHistory($projectId, $branch = 'master')
    {
        $conf = Project::getConf($projectId);
        $revision = Repo::getRevision($conf);
        if ($conf->repo_mode == Project::REPO_MODE_TAG && $conf->repo_type == Project::REPO_GIT) {
            $list = $revision->getTagList();
        } else {
            $list = $revision->getCommitList($branch);
        }
        $this->renderJson($list);
    }

    /**
     * 获取commit之间的文件
     *
     * @param $projectId
     */
    public function actionGetCommitFile($projectId, $start, $end, $branch = 'trunk')
    {
        $conf = Project::getConf($projectId);
        $revision = Repo::getRevision($conf);
        $list = $revision->getFileBetweenCommits($branch, $start, $end);

        $this->renderJson($list);
    }

    /**
     * 上线管理
     *
     * @param $taskId
     * @return string
     * @throws \Exception
     */
    public function actionDeploy($taskId)
    {
        $this->task = TaskModel::find()
            ->where(['id' => $taskId])
            ->with(['project'])
            ->one();
        if (!$this->task) {
            throw new \Exception(yii::t('walle', 'deployment id not exists'));
        }
        if ($this->task->user_id != $this->uid) {
            throw new \Exception(yii::t('w', 'you are not master of project'));
        }

        return $this->render('deploy', [
            'task' => $this->task,
        ]);
    }

    /**
     * 获取上线进度
     *
     * @param $taskId
     */
    public function actionGetProcess($taskId)
    {
        $record = Record::find()
            ->select(['percent' => 'action', 'status', 'memo', 'command'])
            ->where(['task_id' => $taskId,])
            ->orderBy('id desc')
            ->asArray()
            ->one();
        $record['memo'] = stripslashes($record['memo']);
        $record['command'] = stripslashes($record['command']);

        $this->renderJson($record);
    }

    /**
     * get host task process
     *
     * @param $taskId
     * @param $hosts
     */
    public function actionGetTaskProcess($taskId, $hosts)
    {
        $host_array = \app\components\GlobalHelper::str2arr($hosts);

        $taskStateManager = new TaskStateManager();

        $resultArray = [];
        foreach ($host_array as $host) {
            $result = (object)array();
            $result->host = $host;
            $result->status = $taskStateManager->getStatus($taskId, $host);
            $result->progress = $taskStateManager->getProgress($taskId, $host);
            $resultArray[] = $result;
        }

        $this->renderJson($resultArray);
    }

    /**
     * notify auto and manual test result
     */
    public function actionNotifyTestResult()
    {
        $params = \Yii::$app->request->get();
        $redis = new \Redis();
        $conResult = $redis->connect(\Yii::$app->params['redis']['url'], \Yii::$app->params['redis']['port']);
        $code = 0;
        $msg = 'success';
        if (!$conResult) {
            $code = -110;// redis connect failed
            $msg = 'redis connect failed';
        } else {
            if (!isset($params['randomKey'])) {
                $code = -111;// params randomKey
                $msg = 'please provide randomKey param';
            } else {
                $redis->set($params['randomKey'], json_encode($params), 60);
                Command::log('redis value set success:' . $redis->get($params['randomKey']));
            }
        }
        $this->renderJson($params, $code, $msg);
    }

    public function actionManualTestPass($taskId)
    {
        $params = \Yii::$app->request->get();
        $code = 0;
        $msg = 'success';

        $taskManger = new TaskStateManager();
        if (!$taskManger->isRunningTask($taskId)) {
            $code = -110;
            $msg = 'task ' . $taskId . ' is not running!';
        } else {
            $taskManger->setTaskManualTestAllPass($taskId);
        }

        $this->renderJson($params, $code, $msg);
    }

    /**
     * 产生一个上线版本
     */
    private function _makeVersion()
    {
        $version = date("Ymd-His", time());
        $this->task->link_id = $version;

        return $this->task->save();
    }

    /**
     * 检查目录和权限，工作空间的准备
     * 每一个版本都单独开辟一个工作空间，防止代码污染
     *
     * @return bool
     * @throws \Exception
     */
    private function _initWorkspace()
    {
        $sTime = Command::getMs();
        // 本地宿主机工作区初始化
        $this->walleFolder->initLocalWorkspace($this->task);

        // 远程目标目录检查，并且生成版本目录
        $ret = $this->walleFolder->initRemoteVersion($this->task->link_id);
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleFolder, $this->task->id, Record::ACTION_PERMSSION, $duration);

        if (!$ret) {
            throw new \Exception(yii::t('walle', 'init deployment workspace error'));
        }

        return true;
    }

    /**
     * 更新代码文件
     *
     * @return bool
     * @throws \Exception
     */
    private function _revisionUpdate()
    {
        // 更新代码文件
        $revision = Repo::getRevision($this->conf);
        $sTime = Command::getMs();
        $ret = $revision->updateToVersion($this->task); // 更新到指定版本
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($revision, $this->task->id, Record::ACTION_CLONE, $duration);

        if (!$ret) {
            throw new \Exception(yii::t('walle', 'update code error'));
        }

        return true;
    }

    /**
     * 部署前置触发任务
     * 在部署代码之前的准备工作，如git的一些前置检查、vendor的安装（更新）
     *
     * @return bool
     * @throws \Exception
     */
    private function _preDeploy()
    {
        $sTime = Command::getMs();
        $ret = $this->walleTask->preDeploy($this->task->link_id);
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleTask, $this->task->id, Record::ACTION_PRE_DEPLOY, $duration);

        if (!$ret) {
            throw new \Exception(yii::t('walle', 'pre deploy task error'));
        }

        return true;
    }


    /**
     * 部署后置触发任务
     * git代码检出之后，可能做一些调整处理，如vendor拷贝，配置环境适配（mv config-test.php config.php）
     *
     * @return bool
     * @throws \Exception
     */
    private function _postDeploy()
    {
        $sTime = Command::getMs();
        $ret = $this->walleTask->postDeploy($this->task->link_id);
        // 记录执行时间
        $duration = Command::getMs() - $sTime;
        Record::saveRecord($this->walleTask, $this->task->id, Record::ACTION_POST_DEPLOY, $duration);

        if (!$ret) {
            throw new \Exception(yii::t('walle', 'post deploy task error'));
        }

        return true;
    }

    /**
     * 传输文件/目录到指定目标机器
     *
     * @return bool
     * @throws \Exception
     */
    private function _transmission()
    {

        $sTime = Command::getMs();

        if (Project::getAnsibleStatus()) {
            // ansible copy
            $this->walleFolder->ansibleCopyFiles($this->conf, $this->task);
        } else {
            // 循环 scp
            $this->walleFolder->scpCopyFiles($this->conf, $this->task);
        }

        // 记录执行时间
        $duration = Command::getMs() - $sTime;

        Record::saveRecord($this->walleFolder, $this->task->id, Record::ACTION_SYNC, $duration);

        return true;
    }

    /**
     * 执行远程服务器任务集合
     * 对于目标机器更多的时候是一台机器完成一组命令，而不是每条命令逐台机器执行
     *
     * @param string $version
     * @param integer $delay 每台机器延迟执行post_release任务间隔, 不推荐使用, 仅当业务无法平滑重启时使用
     * @throws \Exception
     */
    private function _updateRemoteServers($version, $delay = 0, $rollback = false)
    {
        $cmd = [];
        // pre-release task
        if (($preRelease = WalleTask::getRemoteTaskCommand($this->conf->pre_release, $version))) {
            $cmd[] = $preRelease;
        }
        // link
        if (($linkCmd = $this->walleFolder->getLinkCommand($version))) {
            $cmd[] = $linkCmd;
        }
        // post-release task
        if (($postRelease = WalleTask::getRemoteTaskCommand($this->conf->post_release, $version))) {
            $cmd[] = $postRelease;
        }

        $sTime = Command::getMs();

        if ($rollback || !$this->conf->slb_status) {
            Command::log('====start normal logic:' . $rollback . ' status:' . $this->conf->slb_status);
            // run the task package
            $ret = $this->walleTask->runRemoteTaskCommandPackage($cmd, $delay);
            // 记录执行时间
            $duration = Command::getMs() - $sTime;
            Record::saveRecord($this->walleTask, $this->task->id, Record::ACTION_UPDATE_REMOTE, $duration);
            if (!$ret) {
                throw new \Exception(yii::t('walle', 'update servers error'));
            }
        } else {

            Command::log('====start slb logic');

            // deal slb logic
            $hosts = GlobalHelper::str2arr($this->conf->hosts);
            $slb = SlbFactory::getSlb($this->conf->slb_type);

            $executeResult = true;
            $errMsg = '';
            $errorCommand = '';
            $slbConfig = SlbFactory::getSlbConfig($this->conf);
            $statusManager = new TaskStateManager();
            foreach ($hosts as $host) {
                $statusManager->clearStatus($this->task->id, $host);

                $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_UPDATING_SERVER);
                $ret = $this->walleTask->runRemoteTaskCommandByHost($cmd, $delay, $host);
                if (!$ret) {
                    $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_UPDATE_SERVER_FAILED);
                    $errorCommand = var_export($this->walleTask->getExeCommand());
                    $errMsg = yii::t('walle', 'update servers error');
                    $executeResult = false;
                    break;
                }

                Command::log('step1 remote server:' . $host . ' update files success');

                $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_DOING_AUTO_TEST);

                // slb switch
                $switchRet1 = $slb->setBackendServerWeight($slbConfig, $host, 0);

                $originWeight = $switchRet1['originWeight'];

                if (!$switchRet1) {
                    $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_DO_AUTO_TEST_FAILED);
                    $errorCommand = 'switch slb host:' . $host . ' failed';
                    $errMsg = yii::t('walle', 'slb switch error');
                    $executeResult = false;
                    break;
                }

                Command::log('step2 remote server:' . $host . ' switch slb 0 success!');
                // call test url
                $testRet = $this->requestTestService($host);
                if (!$testRet) {
                    $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_DO_AUTO_TEST_FAILED);
                    $errorCommand = 'execute test host:' . $host . ' failed';
                    $errMsg = yii::t('walle', 'slb test error');
                    $executeResult = false;
                    $slb->setBackendServerWeight($slbConfig, $host, $originWeight);
                    break;
                }

                Command::log('step3 remote server:' . $host . ' test success');

                // manual test
                $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_DOING_MANUAL_TEST);
                $manualWeight = $slbConfig['manualWeight'];
                if ($manualWeight <= 0) {
                    $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_DOING_MANUAL_TEST_FAILED);
                    $errorCommand = 'project conf error manual weight should not be 0';
                    $errMsg = yii::t('walle', 'manual weight error');
                    $executeResult = false;
                    break;
                }

                Command::log('step4 remote server:' . $host . ' update manual doing success');
                // manual test switch slb
                $switchRet = $slb->setBackendServerWeight($slbConfig, $host, $manualWeight);

                if (!$switchRet) {
                    $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_DOING_MANUAL_TEST_FAILED);
                    $errorCommand = 'switch manual slb host:' . $host . ' failed';
                    $errMsg = yii::t('walle', 'slb switch error');
                    $executeResult = false;
                    break;
                }

                Command::log('step5 remote server:' . $host . ' update manual switch slb success');
                $manualTestRet = $this->waitManualTestResult($host);
                if (!$manualTestRet) {
                    $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_DOING_MANUAL_TEST_FAILED);
                    $errorCommand = 'manual test:' . $host . ' failed';
                    $errMsg = yii::t('walle', 'manual test error');
                    $executeResult = false;
                    $slb->setBackendServerWeight($slbConfig, $host, $originWeight);
                    break;
                }

                Command::log('step6 remote server:' . $host . ' update manual test success');
                //slb switch
                $switchRet = $slb->setBackendServerWeight($slbConfig, $host, $originWeight);
                if (!$switchRet) {
                    $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_DOING_MANUAL_TEST_FAILED);
                    $errorCommand = 'final switch slb host:' . $host . ' failed';
                    $errMsg = yii::t('walle', 'slb switch error');
                    $executeResult = false;
                    break;
                }

                $statusManager->setStatus($this->task->id, $host, TaskStateManager::STATE_ONLINE_SUCCESS);
                Command::log('step7 remote server:' . $host . ' switch origin ' . $originWeight . 'slb success');
            }

            if (!$executeResult) {
                $duration = Command::getMs() - $sTime;
                $this->dealSlbError($errorCommand, $duration);
                throw new \Exception($errMsg);
            }

            $duration = Command::getMs() - $sTime;
            Record::saveRecordCustomCommand(1, 'run slb test success', $this->task->id, Record::ACTION_UPDATE_REMOTE, $duration);

            Command::log('update server success!');
        }

        return true;
    }

    private function dealSlbError($command, $duration)
    {
        // rollback version
        $version = $this->conf->version;
        // rollback begin
        $cmd = [];
        // pre-release task
        if (($preRelease = WalleTask::getRemoteTaskCommand($this->conf->pre_release, $version))) {
            $cmd[] = $preRelease;
        }
        // link
        if (($linkCmd = $this->walleFolder->getLinkCommand($version))) {
            $cmd[] = $linkCmd;
        }
        // post-release task
        if (($postRelease = WalleTask::getRemoteTaskCommand($this->conf->post_release, $version))) {
            $cmd[] = $postRelease;
        }

        $this->walleTask->runRemoteTaskCommandPackage($cmd, 0);

        // save record notify ui
        Record::saveRecordCustomCommand(0, $command, $this->task->id, Record::ACTION_UPDATE_REMOTE, $duration);
    }

    private function requestTestService($test_host)
    {
        $url = $this->conf->test_url;
        if (isset($url) && !empty($url)) {
            $curl = curl_init();
            $randomKey = md5($test_host . $this->conf->name . Command::getMs());
            $params = ['ip' => $test_host, 'project' => $this->conf->name, 'randomKey' => $randomKey];
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_URL, $url . "/?" . http_build_query($params));
            $data = curl_exec($curl);
            curl_close($curl);
            $testResult= json_decode($data);
            if (!isset($testResult) || !isset($testResult->id) || $testResult->id <= 0) {
                Command::log('call test service failed');
                return false;
            }

            Command::log('requestTestService step1 call test service success. param:' . json_encode($params));

            //wait test result
            $redis = new \Redis();

            $conResult = $redis->connect(\Yii::$app->params['redis']['url'], \Yii::$app->params['redis']['port']);
            if (!$conResult) {
                Command::log('redis connect error');
                return false;
            }

            Command::log('requestTestService step2 connect redis success');

            $resultJson = '';

            $waitTime = 0;
            $maxWaitTime = \Yii::$app->params['auto.timeout'];// 15min timeout
            while (true) {
                if ($waitTime >= $maxWaitTime) {
                    Command::log('wait result timeout!');
                    break;
                }
                sleep(5);
                if ($redis->exists($randomKey)) {
                    $resultJson = $redis->get($randomKey);
                    Command::log('requestTestService step3 get value:' . $resultJson);
                    break;
                }
                $waitTime += 5;
            }

            // delete redis key
            $redis->delete($randomKey);

            // close
            $redis->close();

            $result = json_decode($resultJson);

            return isset($result) && isset($result->success) && strcmp('true', $result->success) == 0;
        } else {
            return true;
        }

    }

    private function waitManualTestResult($host)
    {

        $redis = new \Redis();

        $conResult = $redis->connect(\Yii::$app->params['redis']['url'], \Yii::$app->params['redis']['port']);
        if (!$conResult) {
            Command::log('manual test redis connect error');
            return false;
        }
        $taskId = $this->task->id;

        $randomKey = TaskStateManager::getManualResultKey($taskId, $host);

        $waitTime = 0;
        $maxWaitTime = \Yii::$app->params['manual.timeout'];// 15min timeout
        $resultJson = false;
        $skipManualTest = false;
        $skipManualTestKey = TaskStateManager::getTaskManualTestAllPassKey($taskId);
        while (true) {
            if ($waitTime >= $maxWaitTime) {
                Command::log('wait manual result timeout!');
                break;
            }

            if ($redis->exists($skipManualTestKey)) {
                $skipManualTest = $redis->get($skipManualTestKey) > 0;
                if ($skipManualTest) {
                    Command::log('waitManualTestResult skip manual test!');
                    break;
                }
            }

            sleep(1);
            if ($redis->exists($randomKey)) {
                $resultJson = $redis->get($randomKey);
                Command::log('waitManualTestResult get value:' . $resultJson);
                break;
            }
            $waitTime += 1;
        }


        // delete redis key
        $redis->delete($randomKey);

        // close
        $redis->close();

        $result = json_decode($resultJson);

        return isset($result) && isset($result->success) && strcmp('true', $result->success) == 0 || $skipManualTest;
    }

    /**
     * 可回滚的版本设置
     *
     * @return int
     */
    private function _enableRollBack()
    {
        $where = ' status = :status AND project_id = :project_id ';
        $param = [':status' => TaskModel::STATUS_DONE, ':project_id' => $this->task->project_id];
        $offset = TaskModel::find()
            ->select(['id'])
            ->where($where, $param)
            ->orderBy(['id' => SORT_DESC])
            ->offset($this->conf->keep_version_num)
            ->limit(1)
            ->scalar();
        if (!$offset) {
            return true;
        }

        $where .= ' AND id <= :offset ';
        $param[':offset'] = $offset;

        return TaskModel::updateAll(['enable_rollback' => TaskModel::ROLLBACK_FALSE], $where, $param);
    }

    /**
     * 只保留最大版本数，其余删除过老版本
     */
    private function _cleanRemoteReleaseVersion()
    {
        return $this->walleTask->cleanUpReleasesVersion();
    }

    /**
     * 执行远程服务器任务集合回滚，只操作pre-release、link、post-release任务
     *
     * @param $version
     * @throws \Exception
     */
    public function _rollback($version)
    {
        return $this->_updateRemoteServers($version, 0, true);
    }

    /**
     * 收尾工作，清除宿主机的临时部署空间
     */
    private function _cleanUpLocal($version = null)
    {
        // 创建链接指向
        $this->walleFolder->cleanUpLocal($version);

        return true;
    }

}
