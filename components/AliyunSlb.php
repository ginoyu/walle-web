<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/11/18
 * Time: 8:43 PM
 */

namespace app\components;


class AliyunSlb implements ISlb
{
    const CURL_SLB_URL = "http://slb.aliyuncs.com";
    const CURL_ECS_URL = "http://ecs.aliyuncs.com";
    const CURL_FORMATE = "JSON";
    const CURL_SLB_VERSION = "2014-05-15";
    const CURL_ECS_VERSION = "2014-05-26";
    const CURL_SIGNATURE_METHOD = "HMAC-SHA1";
    const CURL_SIGNATURE_VERSION = "1.0";

    // public params
    const KEY_FORMATE = "Format";
    const KEY_VERSION = "Version";
    const KEY_ACCESSKEYID = "AccessKeyId";
    const KEY_ACCESSKEY_SECRECT = "AccessKeySecret";
    const KEY_SIGNATURE = "Signature";
    const KEY_SIGNATURE_METHOD = "SignatureMethod";
    const KEY_TIMESTAMP = "Timestamp";
    const KEY_SIGNATURE_VERSION = "SignatureVersion";
    const KEY_SIGNATURE_NONCE = "SignatureNonce";
    const KEY_PAGE_SIZE = 'PageSize';

    // private params
    const KEY_ACTION = "Action";
    const KEY_REGION_ID = "RegionId";
    const KEY_LISTENER_PORT = '80';
    const KEY_LOAD_BALANCE_ID = "LoadBalancerId";
    const KEY_BACKEND_SERVERS = "BackendServers";
    const KEY_SERVERID = "ServerId";
    const KEY_WEIGHT = "Weight";
    const KEY_INSTANCES = 'InstanceIds';

    // private action
    const ACTION_DESCRIBE_REGIONS = "DescribeRegions";
    const ACTION_DESCRIBE_LOAD_BALANCERS = "DescribeLoadBalancers";
    const ACTION_DESCRIBE_HEALTH_STATUS = "DescribeHealthStatus";
    const ACTION_DESCRIBE_LOAD_BALANCER_ATTRIBUTE = "DescribeLoadBalancerAttribute";
    const ACTION_SET_BACKEND_SERVERS = "SetBackendServers";
    const ACTION_DESCRIBE_INSTANCES = "DescribeInstances";


    // param key
    const PARAM_KEY_SERVER_ID = "serverId";
    const PARAM_KEY_SERVER_IP = "ip";

    private $mAccessKeyId = "";
    private $mAccessKeySecret = "";
    private $mRegionId = "cn-beijing";
    private $mLoadBanceId = "lb-2ze7bcize8zopo9mcyuwn";

    private function loadConfig($config)
    {
        $this->mAccessKeyId = $config[self::KEY_ACCESSKEYID];
        $this->mAccessKeySecret = $config[self::KEY_ACCESSKEY_SECRECT];
        $this->mRegionId = $config[self::KEY_REGION_ID];
        $this->mLoadBanceId = $config[self::KEY_LOAD_BALANCE_ID];
    }

    /**
     * get ecs ip list by slb config
     **/
    public function getEcsIpList($config = [])
    {
        $data = $this->describeLoadBalancerAttribute($config);

        Command::log('get ecs ip describeLoadBalancerAttribute:' . $data);

        $balanceInfo = json_decode($data);
        $serverIds = [];
        if (isset($balanceInfo) && isset($balanceInfo->BackendServers) && isset($balanceInfo->BackendServers->BackendServer)) {
            $backendServer = $balanceInfo->BackendServers->BackendServer;
            foreach ($backendServer as $server) {
                $serverIds[] = $server->ServerId;
            }
        }


        $data = $this->getInstances($config, $serverIds);

        Command::log('get ecs ip list:' . json_encode($serverIds) . ' ===' . $data);
        $ecsInstance = json_decode($data);

        $results = [];
        if (isset($ecsInstance) && isset($ecsInstance->Instances) && isset($ecsInstance->Instances->Instance)) {
            $ecsInstances = $ecsInstance->Instances->Instance;
            foreach ($ecsInstances as $instance) {
                if (in_array($instance->InstanceId, $serverIds)) {
                    $ip = "";

                    if (isset($instance->NetworkInterfaces) && isset($instance->NetworkInterfaces->NetworkInterface)) {
                        $ip = $instance->NetworkInterfaces->NetworkInterface[0]->PrimaryIpAddress;
                    } else if (isset($instance->InnerIpAddress->IpAddress)) {
                        $ip = $instance->InnerIpAddress->IpAddress[0];
                    } else {
                        Command::log($instance->InstanceId . " has not ip params!");
                    }
                    array_push($results, [self::PARAM_KEY_SERVER_ID => $instance->InstanceId, self::PARAM_KEY_SERVER_IP => $ip]);
                }
            }
        }

        return $results;

    }

    public function getInstances($config, $intances = [])
    {
        $this->loadConfig($config);
        $params = [
            self::KEY_ACTION => self::ACTION_DESCRIBE_INSTANCES,
            self::KEY_VERSION => self::CURL_ECS_VERSION,
            self::KEY_REGION_ID => $this->mRegionId,
            self::KEY_INSTANCES => json_encode($intances),
            self::KEY_PAGE_SIZE => 100
        ];
        $data = $this->requestAliSlbService(self::CURL_ECS_URL, $params);
        return $data;
    }

    /**
     * set backend server weight
     **/
    public function setBackendServerWeight($config = [], $ip, $weight)
    {
        Command::log('setBackendServerWeight ip:' . $ip . ' weight:' . $weight);
        /*$this->loadConfig($config);
        $params = [
            self::KEY_ACTION => self::ACTION_SET_BACKEND_SERVERS,
            self::KEY_VERSION => self::CURL_SLB_VERSION,
            self::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId,
            self::KEY_BACKEND_SERVERS => json_encode($ecs)
        ];
        $data = $this->requestAliSlbService(self::CURL_SLB_URL, $params);
        return $data;*/
        return $this->setBackendServerWeightWithIp($config, $ip, $weight);
    }

    /**
     * set backend server weight
     **/
    private function setBackendServerWeightWithIp($config = [], $ip, $weight)
    {
        Command::log('setBackendServerWeightWithIp ip:' . $ip . ' weight:' . $weight);
        $this->loadConfig($config);
        $serverInfos = $this->getEcsIpList($config);
        $serverId = "";
        foreach ($serverInfos as $serverInfo) {
            if (strcmp($serverInfo[self::PARAM_KEY_SERVER_IP], $ip) == 0) {
                $serverId = $serverInfo[self::PARAM_KEY_SERVER_ID];
                break;
            }
        }

        if (!$serverId) {
            Command::log('setBackendServerWeightWithIp ip:' . $ip . ' serverId not exist');
            return false;
        }

//        Command::log('changed serverId:' . $serverId);

        // get origin weight
        $loadBancerResult = $this->describeLoadBalancerAttribute($config);
        $loadBancerInfo = json_decode($loadBancerResult);

        $originWeight = -1;
        if (isset($loadBancerInfo) && isset($loadBancerInfo->BackendServers) && isset($loadBancerInfo->BackendServers->BackendServer)) {
            foreach ($loadBancerInfo->BackendServers->BackendServer as $result) {
                if (strcmp($result->ServerId, $serverId) == 0) {
                    $originWeight = $result->Weight;
                    break;
                }
            }
        }

        $ecs = [[self::KEY_SERVERID => $serverId, self::KEY_WEIGHT => $weight]];
        $params = [
            self::KEY_ACTION => self::ACTION_SET_BACKEND_SERVERS,
            self::KEY_VERSION => self::CURL_SLB_VERSION,
            self::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId,
            self::KEY_BACKEND_SERVERS => json_encode($ecs)
        ];
        $data = $this->requestAliSlbService(self::CURL_SLB_URL, $params);

        $resultObj = json_decode($data);

        $success = false;
        // get result
        if (isset($resultObj) && isset($resultObj->BackendServers) && isset($resultObj->BackendServers->BackendServer)) {
            foreach ($resultObj->BackendServers->BackendServer as $result) {
                if (strcmp($result->ServerId, $serverId) == 0) {
                    if ($result->Weight == $weight) {
                        $success = true;
                        break;
                    }
                }
            }
        }
//        Command::log('setBackendServerWeightWithIp ip:' . $ip . ' result:' . $data . ' success:' . ($success ? 'true' : 'false') . ' originWeight:' . $originWeight);

        return ['success' => $success, 'originWeight' => $originWeight];
    }

    public function describeRegions($config = [])
    {
        $this->loadConfig($config);
        $params = [
            self::KEY_ACTION => self::ACTION_DESCRIBE_REGIONS,
            self::KEY_VERSION => self::CURL_SLB_VERSION
        ];
        $data = $this->requestAliSlbService(self::CURL_SLB_URL, $params);
        return $data;
    }

    public function describeLoadBalancerAttribute($config = [])
    {
        $this->loadConfig($config);
        $params = [
            self::KEY_ACTION => self::ACTION_DESCRIBE_LOAD_BALANCER_ATTRIBUTE,
            self::KEY_REGION_ID => $this->mRegionId,
            self::KEY_LOAD_BALANCE_ID => $this->mLoadBanceId,
            self::KEY_VERSION => self::CURL_SLB_VERSION
        ];
        $data = $this->requestAliSlbService(self::CURL_SLB_URL, $params);
        return $data;
    }

    private function requestAliSlbService($host, $params)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_URL, $this->getUrl($host, $params));

        $data = curl_exec($curl);

        curl_close($curl);
        return $data;
    }

    private function getUrl($host, $params, $method = 'GET')
    {
        $publicParams = [
            self::KEY_FORMATE => self::CURL_FORMATE,
//            self::KEY_VERSION => self::CURL_ECS_VERSION,
            self::KEY_ACCESSKEYID => $this->mAccessKeyId,
            self::KEY_SIGNATURE_METHOD => self::CURL_SIGNATURE_METHOD,
            self::KEY_TIMESTAMP => $this->getTimeStamp(),
            self::KEY_SIGNATURE_VERSION => self::CURL_SIGNATURE_VERSION,
            self::KEY_SIGNATURE_NONCE => $this->getUuid()

        ];
        $params = array_merge($params, $publicParams);

        $params[self::KEY_SIGNATURE] = $this->compute_signature($params, $method);

        $url = $host . "/?" . http_build_query($params);
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

    /**
     * get weight by ips
     * @param array $config
     * @param array $ips
     * @return mixed
     */
    public function getWeightAndNameByIps($config = [], $ips = [])
    {
        $data = $this->describeLoadBalancerAttribute($config);

//        Command::log('get ecs ip describeLoadBalancerAttribute:' . $data);

        $balanceInfo = json_decode($data);
        $serverIds = [];

        if (isset($balanceInfo) && isset($balanceInfo->BackendServers) && isset($balanceInfo->BackendServers->BackendServer)) {
            $backendServer = $balanceInfo->BackendServers->BackendServer;
            foreach ($backendServer as $server) {
                $serverIds[$server->ServerId] = $server->Weight;
            }
        }

        $data = $this->getInstances($config, array_keys($serverIds));

//        Command::log('get ecs ip list:' . json_encode($serverIds) . ' ===' . $data);
        $ecsInstance = json_decode($data);

        $results = [];
        $weights = [];
        $names = [];
        if (isset($ecsInstance) && isset($ecsInstance->Instances) && isset($ecsInstance->Instances->Instance)) {
            $ecsInstances = $ecsInstance->Instances->Instance;
            foreach ($ecsInstances as $instance) {
                if (key_exists($instance->InstanceId, $serverIds)) {
                    $ip = "";

                    if (isset($instance->NetworkInterfaces) && isset($instance->NetworkInterfaces->NetworkInterface)) {
                        $ip = $instance->NetworkInterfaces->NetworkInterface[0]->PrimaryIpAddress;
                    } else if (isset($instance->InnerIpAddress->IpAddress)) {
                        $ip = $instance->InnerIpAddress->IpAddress[0];
                    } else {
                        Command::log($instance->InstanceId . " has not ip params!");
                    }
                    $weights[$ip] = $serverIds[$instance->InstanceId];
                    $names[$ip] = $instance->InstanceId;
                }
            }
        }

        return $results = array('weight' => $weights, 'name' => $names);
    }
}
