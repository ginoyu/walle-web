<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/26/18
 * Time: 3:48 PM
 */

namespace app\components;


class TaskStateManager
{
    private $redis;
    /**
     * updating host server state
     */
    const STATE_UPDATING_SERVER = 1;

    /**
     * doing host server auto test state
     */
    const STATE_DOING_AUTO_TEST = 2;

    /**
     * doing host server manual test state
     */
    const STATE_DOING_MANUAL_TEST = 3;

    /**
     * online host server success state
     */
    const STATE_ONLINE_SUCCESS = 4;

    /**
     * updating host server failed state
     */
    const STATE_UPDATE_SERVER_FAILED = 5;

    /**
     * doing host server auto test failed state
     */
    const STATE_DO_AUTO_TEST_FAILED = 6;

    /**
     * doing host server manual test failed state
     */
    const STATE_DOING_MANUAL_TEST_FAILED = 7;

    public function __construct()
    {
        $this->redis = new \Redis();

        $conResult = $this->redis->connect(\Yii::$app->params['redis']['url'], \Yii::$app->params['redis']['port']);
        if (!$conResult) {
            Command::log('TaskStateManager redis connect error');
        }
    }

    public function __destruct()
    {
        $this->redis->close();
    }

    public function setStatus($taskId, $host, $status)
    {
        $this->redis->setex($this->getStatusKey($taskId, $host), 3600 * 24, $status);
//        if ($this->checkValidStatus($taskId, $host, $status)) {
//        } else {
//            Command::log('invalid status:' . $status);
//        }
    }

    public function getStatus($taskId, $host)
    {
        $key = $this->getStatusKey($taskId, $host);
        $status = self::STATE_UPDATING_SERVER;
        if ($this->redis->exists($key)) {
            $status = $this->redis->get($key);
        }
        return $status;
    }

    public function getProgress($taskId, $host)
    {
        $progress = 0;
        $status = $this->getStatus($taskId, $host);
        switch ($status) {
            case self::STATE_UPDATING_SERVER:
            case self::STATE_UPDATE_SERVER_FAILED:
                $progress = 0;
                break;
            case self::STATE_DOING_AUTO_TEST:
            case self::STATE_DO_AUTO_TEST_FAILED:
                $progress = 33;
                break;
            case self::STATE_DOING_MANUAL_TEST:
            case self::STATE_DOING_MANUAL_TEST_FAILED:
                $progress = 66;
                break;
            case self::STATE_ONLINE_SUCCESS:
                $progress = 100;
                break;
        }
        return $progress;
    }

    public function clearStatus($taskId, $host)
    {
        $this->redis->delete($this->getStatusKey($taskId, $host));
    }

    private function checkValidStatus($taskId, $host, $status)
    {
        $isValidStatus = false;
        // get last status
        $statusKey = $this->getStatusKey($taskId, $host);
        $lastStatus = -1;
        if ($this->redis->exists($statusKey)) {
            $lastStatus = $this->redis->get($statusKey);
            switch ($lastStatus) {
                case self::STATE_UPDATING_SERVER:
                    $isValidStatus = in_array($lastStatus, [self::STATE_DOING_AUTO_TEST, self::STATE_UPDATE_SERVER_FAILED, self::STATE_UPDATING_SERVER]);
                    break;
                case self::STATE_DOING_AUTO_TEST:
                    $isValidStatus = in_array($lastStatus, [self::STATE_DO_AUTO_TEST_FAILED, self::STATE_DOING_MANUAL_TEST, self::STATE_DOING_AUTO_TEST]);
                    break;
                case self::STATE_DOING_MANUAL_TEST:
                    $isValidStatus = in_array($lastStatus, [self::STATE_ONLINE_SUCCESS, self::STATE_DOING_MANUAL_TEST_FAILED, self::STATE_DOING_MANUAL_TEST]);
                    break;
                /*case self::STATE_ONLINE_SUCCESS:
                    break;
                case self::STATE_UPDATE_SERVER_FAILED:
                    break;
                case self::STATE_DO_AUTO_TEST_FAILED:
                    break;
                case self::STATE_DOING_MANUAL_TEST_FAILED:
                    break;*/
            }
        } else {
            if ($status == self::STATE_UPDATING_SERVER) {
                $isValidStatus = true;
            }
        }

        if (!$isValidStatus) {
            Command::log('invalid status:' . $status . ' last status:' . $lastStatus);
        }

        return $isValidStatus;

    }

    public function setTaskManualTestAllPass($taskId)
    {
        $this->redis->set($this->getTaskManualTestAllPassKey($taskId), $taskId);
    }

    public function getTaskManualTestAllPass($taskId)
    {
        $result = 0;
        if ($this->redis->exists($this->getTaskManualTestAllPassKey($taskId))) {
            $result = $this->redis->get($this->getTaskManualTestAllPassKey($taskId));
        }
        return $result;
    }

    public function setRunningTask($taskId)
    {
        $this->redis->set($this->getRunningTaskKey($taskId), $taskId);
    }

    public function clearRunningTaskState($taskId)
    {
        if ($this->redis->exists($this->getRunningTaskKey($taskId))) {
            $this->redis->delete($this->getRunningTaskKey($taskId));
        }

        if ($this->redis->exists($this->getTaskManualTestAllPassKey($taskId))) {
            $this->redis->delete($this->getTaskManualTestAllPassKey($taskId));
        }
    }

    public function isRunningTask($taskId)
    {
        $result = 0;
        if ($this->redis->exists($this->getRunningTaskKey($taskId))) {
            $result = $this->redis->get($this->getRunningTaskKey($taskId));
        }
        return $result;
    }

    private function getRunningTaskKey($taskId)
    {
        return 'running_task' . $taskId;
    }

    public static function getTaskManualTestAllPassKey($taskId)
    {
        return 'manual_test_all_pass_' . $taskId;
    }

    public function getStatusKey($taskId, $host)
    {
        return md5($taskId . $host . 'status');
    }

    public static function getManualResultKey($taskId, $host)
    {
        return md5($taskId . $host . 'manual_test_result');
    }

    public static function getMinFlowTestResultKey($taskId, $host)
    {
        return md5($taskId . $host . 'min_flow_test');
    }


}