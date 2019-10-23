<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/15/18
 * Time: 12:02 PM
 */

namespace app\components;


use app\models\slb\AliyunSlbConfig;

class SlbFactory
{
    const PARAM_ALIYUN = "aliyun_slb";

    public static function getSlb($type = ISlb::SLB_TYPE_ALIYUN)
    {
        switch ($type) {
            case ISlb::SLB_TYPE_ALIYUN:
                return new AliyunSlb();
            default:
                throw new \Exception(\yii::t('walle', 'unknown slb type'));
                break;
        }
    }

    public static function composeSlbConfig($type = ISlb::SLB_TYPE_ALIYUN, $params = [])
    {
        switch ($type) {
            case ISlb::SLB_TYPE_ALIYUN:
                {
                    $config = new AliyunSlbConfig();
//                    $config->accessKeyId = \yii::$app->params[self::PARAM_ALIYUN][AliyunSlb::KEY_ACCESSKEYID];
//                    $config->accessKeySecret = \yii::$app->params[self::PARAM_ALIYUN][AliyunSlb::KEY_ACCESSKEY_SECRECT];
                    $config->regionId = $params[AliyunSlb::KEY_REGION_ID];
                    $config->loadBanceId = $params[AliyunSlb::KEY_LOAD_BALANCE_ID];
                    return $config;
                }

            default:
                throw new \Exception(\yii::t('walle', 'unknown slb type'));
                break;
        }
    }

    public static function getSlbConfig($project)
    {
        switch ($project->slb_type) {
            case ISlb::SLB_TYPE_ALIYUN:
                {
                    $aliyunConfig = json_decode($project->slb_config);
                    $config = self::getPrivateSlbConfigArray(ISlb::SLB_TYPE_ALIYUN);
                    $config = array_merge($config, [AliyunSlb::KEY_REGION_ID => $aliyunConfig->regionId, AliyunSlb::KEY_LOAD_BALANCE_ID => $aliyunConfig->loadBanceId]);
                    return $config;
                }

            default:
                throw new \Exception(\yii::t('walle', 'unknown slb type'));
                break;
        }
    }

    public static function getSlbId($project)
    {
        if ($project && $project->slb_status) {
            $slbConfig = self::getSlbConfig($project);
            switch ($project->slb_type) {
                case ISlb::SLB_TYPE_ALIYUN:
                    return $slbConfig[AliyunSlb::KEY_LOAD_BALANCE_ID];
            }
        }
        return false;
    }

    public static function getPrivateSlbConfigArray($slbType = ISlb::SLB_TYPE_ALIYUN)
    {
        switch ($slbType) {
            case ISlb::SLB_TYPE_ALIYUN:
                {
                    return [
                        AliyunSlb::KEY_ACCESSKEYID => \yii::$app->params[self::PARAM_ALIYUN][AliyunSlb::KEY_ACCESSKEYID],
                        AliyunSlb::KEY_ACCESSKEY_SECRECT => \yii::$app->params[self::PARAM_ALIYUN][AliyunSlb::KEY_ACCESSKEY_SECRECT]
                    ];
                }

            default:
                throw new \Exception(\yii::t('walle', 'unknown slb type'));
                break;
        }
    }

}