<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/24/18
 * Time: 10:30 AM
 */

namespace app\components;


use app\models\Group;

class PermissionHelper
{
    /**
     * @param user_type
     * @param task_status
     * 判断是否为当前流程的审核人员
     */
    public static function isAudit($user_type, $task_status)
    {
        $isAduit = false;
        switch ($task_status) {
            case \app\models\Task::STATUS_SUBMIT:
            case \app\models\Task::STATUS_REFUSE:
                $isAduit = Group::isAdmin($user_type);
                break;
            case \app\models\Task::STATUS_TEC_LEADER_PASS:
            case \app\models\Task::STATUS_TEST_LEADER_FAILED:
                $isAduit = Group::isTester($user_type);
                break;
            case \app\models\Task::STATUS_TEST_LEADER_PASS:
            case \app\models\Task::STATUS_OPS_LEADER_FAILED:
            case \app\models\Task::STATUS_PASS:
                $isAduit = Group::isOperation($user_type);
                break;
        }

        return $isAduit;
    }

    /**
     * @param $user_type
     * return if is aduit user
     */
    public static function isAduitUserType($user_type)
    {
        return $user_type > Group::TYPE_USER;
    }

    public static function isAdminChecked($task_status)
    {
        $isChecked = true;
        if ($task_status == \app\models\Task::STATUS_REFUSE || $task_status == \app\models\Task::STATUS_SUBMIT) {
            $isChecked = false;
        }
        return $isChecked;
    }

    public static function isTestChecked($task_status)
    {
        $isChecked = false;
        if ($task_status == \app\models\Task::STATUS_TEST_LEADER_PASS ||
            $task_status == \app\models\Task::STATUS_OPS_LEADER_FAILED ||
            $task_status == \app\models\Task::STATUS_PASS ||
            $task_status == \app\models\Task::STATUS_DONE ||
            $task_status == \app\models\Task::STATUS_FAILED) {
            $isChecked = true;
        }
        return $isChecked;
    }

    public static function isOpsChecked($task_status)
    {
        $isChecked = false;
        if ($task_status == \app\models\Task::STATUS_PASS ||
            $task_status == \app\models\Task::STATUS_DONE ||
            $task_status == \app\models\Task::STATUS_FAILED) {
            $isChecked = true;
        }
        return $isChecked;
    }

    public static function getStatus($user_type, $operation, $last_status)
    {
        $status = -1;
        switch ($user_type) {
            case Group::TYPE_ADMIN:
                if (in_array($last_status, [\app\models\Task::STATUS_SUBMIT, \app\models\Task::STATUS_REFUSE, \app\models\Task::STATUS_TEC_LEADER_PASS])) {
                    $status = $operation ? \app\models\Task::STATUS_TEC_LEADER_PASS : \app\models\Task::STATUS_REFUSE;
                }
                break;
            case Group::TYPE_TESTER:
                if (in_array($last_status, [\app\models\Task::STATUS_TEST_LEADER_PASS, \app\models\Task::STATUS_TEST_LEADER_FAILED, \app\models\Task::STATUS_TEC_LEADER_PASS])) {
                    $status = $operation ? \app\models\Task::STATUS_TEST_LEADER_PASS : \app\models\Task::STATUS_TEST_LEADER_FAILED;
                }
                break;
            case Group::TYPE_OPERATIONS:
                if (in_array($last_status, [\app\models\Task::STATUS_TEST_LEADER_PASS, \app\models\Task::STATUS_PASS, \app\models\Task::STATUS_OPS_LEADER_FAILED])) {
                    $status = $operation ? \app\models\Task::STATUS_PASS : \app\models\Task::STATUS_OPS_LEADER_FAILED;
                }
                break;
        }
        return $status;
    }

    /**
     * 获取tec leader可操作的状态
     * @return array
     */
    public static function getTecLeaderOpStatus()
    {
        return [\app\models\Task::STATUS_SUBMIT, \app\models\Task::STATUS_REFUSE, \app\models\Task::STATUS_TEC_LEADER_PASS];
    }

    /**
     * 获取test leader可操作的状态
     * @return array
     */
    public static function getTestLeaderOpStatus()
    {
        return [\app\models\Task::STATUS_TEC_LEADER_PASS, \app\models\Task::STATUS_TEST_LEADER_FAILED, \app\models\Task::STATUS_TEST_LEADER_PASS];
    }

    /**
     * 获取ops leader可操作的状态
     * @return array
     */
    public static function getOpsLeaderOpStatus()
    {
        return [\app\models\Task::STATUS_TEST_LEADER_PASS, \app\models\Task::STATUS_PASS, \app\models\Task::STATUS_OPS_LEADER_FAILED];
    }
}