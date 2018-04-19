<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 4/13/18
 * Time: 8:14 PM
 */

namespace app\components;


class AlertUtils
{
    public static function getAuditSubject(\app\models\Task $task)
    {
        return "[部署平台] $task->commit_id $task->title  上线审核通知";
    }

    public static function getAutoTestResult(\app\models\Task $task, $host)
    {
        return "[部署平台] $task->commit_id $task->title 机器：$host 自动化测试成功";
    }

    public static function getMaunalTestResult(\app\models\Task $task, $host, $userName)
    {
        return "[部署平台] $task->commit_id $task->title 机器：$host $userName 手动测试成功";
    }

    public static function getOnlineSuccess(\app\models\Task $task)
    {
        return "[部署平台] $task->commit_id $task->title  上线成功";
    }

    public static function getOnlineFailed(\app\models\Task $task)
    {
        return "[部署平台] $task->commit_id $task->title  上线失败";
    }

    public static function getOnlineStart(\app\models\Task $task)
    {
        return "[部署平台] $task->commit_id $task->title  开始上线";
    }

    public static function getTime($time)
    {
        $hour = intval($time / 3600);
        $min = intval($time % 3600 / 60);
        $second = intval($time % 60);
        $result = ($hour > 0 ? strval($hour) . '小时' : '') . ($min > 0 ? strval($min) . '分钟' : '') . ($second > 0 ? strval($second) . '秒' : '');
        return $result;
    }
}