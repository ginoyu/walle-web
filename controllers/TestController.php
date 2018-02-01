<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/10/18
 * Time: 8:26 PM
 */

namespace app\controllers;

use app\components\AliyunSlb;
use app\components\Controller;
use app\components\DingDingBot;
use app\components\ISlb;
use app\components\SlbFactory;

class TestController extends Controller
{
    const CURL_SLB_URL = "http://slb.aliyuncs.com";
    const CURL_ECS_URL = "http://ecs.aliyuncs.com";
    const CURL_FORMATE = "JSON";
    const CURL_SLB_VERSION = "2014-05-15";
    const CURL_ECS_VERSION = "2014-05-26";
    const CURL_SIGNATURE_METHOD = "HMAC-SHA1";
    const CURL_SIGNATURE_VERSION = "1.0";

    // TODO save in local config
    private $mAccessKeyId = "LTAIcPaGvBBVwSsC";
    private $mAccessKeySecret = "nAk4SNMBHUofRa5MVkGKjpwlJ06tkA";
    private $mRegionId = "cn-beijing";
    private $mLoadBanceId = "lb-2ze7bcize8zopo9mcyuwn";

    // public params
    const KEY_FORMATE = "Format";
    const KEY_VERSION = "Version";
    const KEY_ACCESSKEYID = "AccessKeyId";
    const KEY_SIGNATURE = "Signature";
    const KEY_SIGNATURE_METHOD = "SignatureMethod";
    const KEY_TIMESTAMP = "Timestamp";
    const KEY_SIGNATURE_VERSION = "SignatureVersion";
    const KEY_SIGNATURE_NONCE = "SignatureNonce";

    // private params
    const KEY_ACTION = "Action";
    const KEY_REGION_ID = "RegionId";
    const KEY_LISTENER_PORT = '80';
    const KEY_LOAD_BALANCE_ID = "LoadBalancerId";

    // private action
    const ACTION_DESCRIBE_REGIONS = "DescribeRegions";
    const ACTION_DESCRIBE_LOAD_BALANCERS = "DescribeLoadBalancers";
    const ACTION_DESCRIBE_HEALTH_STATUS = "DescribeHealthStatus";
    const ACTION_DESCRIBE_LOAD_BALANCER_ATTRIBUTE = "DescribeLoadBalancerAttribute";

    public function actionDescribeRegions()
    {
        $slb = new AliyunSlb();
        $data = $slb->describeRegions([
            AliyunSlb::KEY_ACCESSKEYID => $this->mAccessKeyId,
            AliyunSlb::KEY_ACCESSKEY_SECRECT => $this->mAccessKeySecret,
            AliyunSlb::KEY_REGION_ID => $this->mRegionId,
            AliyunSlb::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId
        ]);
        print_r($data);
    }

    public function actionBackendServerWeight()
    {
        $slb = new AliyunSlb();
//        $data = $slb->setBackendServerWeight([
//            AliyunSlb::KEY_ACCESSKEYID => $this->mAccessKeyId,
//            AliyunSlb::KEY_ACCESSKEY_SECRECT => $this->mAccessKeySecret,
//            AliyunSlb::KEY_REGION_ID => $this->mRegionId,
//            AliyunSlb::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId
//        ], [["serverId" => "i-2ze420md65peqcgdkkeh", "weight" => 10], ["serverId" => "i-2zefqwyc8npombv9ox8m", "weight" => 20]]);

        $data = $slb->setBackendServerWeight([
            AliyunSlb::KEY_ACCESSKEYID => $this->mAccessKeyId,
            AliyunSlb::KEY_ACCESSKEY_SECRECT => $this->mAccessKeySecret,
            AliyunSlb::KEY_REGION_ID => $this->mRegionId,
            AliyunSlb::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId
        ],'39.106.208.2',100);
        print_r($data);
    }

    public function actionDescribeLoadBalancers()
    {
        $params = [
            self::KEY_ACTION => self::ACTION_DESCRIBE_LOAD_BALANCERS,
            self::KEY_REGION_ID => $this->mRegionId
        ];
        $data = $this->requestAliSlbService($params);
        print_r($data);
    }

    public function actionDescribeHealthStatus()
    {
        $params = [
            self::KEY_ACTION => self::ACTION_DESCRIBE_HEALTH_STATUS,
            self::KEY_REGION_ID => $this->mRegionId
        ];
        $data = $this->requestAliSlbService($params);
        print_r($data);
    }

    public function actionDescribeLoadBalancerAttribute()
    {
        $slb = new AliyunSlb();
        $data = $slb->describeLoadBalancerAttribute([
            AliyunSlb::KEY_ACCESSKEYID => $this->mAccessKeyId,
            AliyunSlb::KEY_ACCESSKEY_SECRECT => $this->mAccessKeySecret,
            AliyunSlb::KEY_REGION_ID => $this->mRegionId,
            AliyunSlb::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId
        ]);
        print_r($data);
    }

    public function actionDescribeInstances()
    {
//        $params = [
//            self::KEY_ACTION => "DescribeInstances",
//            self::KEY_REGION_ID => $this->mRegionId,
//            self::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId
//        ];
//        $data = $this->requestAliSlbService($params);
//        print_r($data);

        $slb = new AliyunSlb();
        $data = $slb->getInstances([
            AliyunSlb::KEY_ACCESSKEYID => $this->mAccessKeyId,
            AliyunSlb::KEY_ACCESSKEY_SECRECT => $this->mAccessKeySecret,
            AliyunSlb::KEY_REGION_ID => $this->mRegionId,
            AliyunSlb::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId
        ]);
        print_r($data);
    }


    public function actionGetEcsIp()
    {
        $slb = new AliyunSlb();
        $config = SlbFactory::getPrivateSlbConfigArray(ISlb::SLB_TYPE_ALIYUN);
        $config = array_merge($config,[AliyunSlb::KEY_REGION_ID => $this->mRegionId,
            AliyunSlb::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId]);
//        $slb->getEcsIpList([
//            AliyunSlb::KEY_ACCESSKEYID => $this->mAccessKeyId,
//            AliyunSlb::KEY_ACCESSKEY_SECRECT => $this->mAccessKeySecret,
//            AliyunSlb::KEY_REGION_ID => $this->mRegionId,
//            AliyunSlb::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId
//        ]);
        $slb->getEcsIpList($config);
    }

    public function actionSendMessage($message = '')
    {
        $dingding = new DingDingBot('a612b4ec7f38c07d6653bcfabf8aa423dd70d87a75b2a5ae0b96f9b398d9a252');
        $dingding->sendToAll($message);
    }

    private function requestAliSlbService($params)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $this->getUrl($params));

        $data = curl_exec($curl);

        curl_close($curl);
        return $data;
    }

    private function getUrl($params = [], $method = 'GET')
    {
        $publicParams = [
            self::KEY_FORMATE => self::CURL_FORMATE,
            self::KEY_VERSION => self::CURL_ECS_VERSION,
            self::KEY_ACCESSKEYID => $this->mAccessKeyId,
            self::KEY_SIGNATURE_METHOD => self::CURL_SIGNATURE_METHOD,
            self::KEY_TIMESTAMP => $this->getTimeStamp(),
            self::KEY_SIGNATURE_VERSION => self::CURL_SIGNATURE_VERSION,
            self::KEY_SIGNATURE_NONCE => $this->getUuid()

        ];
        $params = array_merge($params, $publicParams);

        $params[self::KEY_SIGNATURE] = $this->compute_signature($params, $method);

        $url = self::CURL_ECS_URL . "/?" . http_build_query($params);
        return $url;

    }

    private function compute_signature($params, $method = 'GET')
    {
        ksort($params);
        $stringToSign = strtoupper($method) . "&" . $this->percentEncode("/") . "&";
        $keyString = "";
        foreach ($params as $key => $value) {
            $keyString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $keyString = trim($keyString, '&');
        $stringToSign .= $this->percentEncode($keyString);

        $key = $this->mAccessKeySecret . '&';

        $result = hash_hmac("sha1", $stringToSign, $key, true);

        return base64_encode($result);
    }

    private function getTimeStamp()
    {
        date_default_timezone_set("UTC");
        $timestamp = date('Y-m-d\TH:i:s\Z', time());
        date_default_timezone_set("Asia/Shanghai");
        return $timestamp;
    }

    private function getUuid()
    {
        mt_srand((double)microtime() * 10000);
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
        return $uuid;
    }

    private function percentEncode($str)
    {
        $str = urlencode($str);
        $str = str_replace('+', '%20', $str);
        $str = str_replace('*', '%2A', $str);
        $str = str_replace('%7E', '~', $str);
        return $str;
    }
}