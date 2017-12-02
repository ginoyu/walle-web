<?php
/* *****************************************************************
 * @Author: libing
 * @Created Time : 日  12/2 14:20:00 2017
 *
 * @File Name: command/Svn.php
 * @Description:
 * *****************************************************************/

namespace app\components;

use Yii;
use yii\httpclient\Client;

class AliyunSlb
{
    private $machines = [];
    private $slbs = [];
    private $accessKey = '';
    private $accessSecret = '';
    private $address = '';

    private $curl_format = 'JSON';
    private $curl_version = '2014-05-15';
    private $curl_signature_version = '1.0';
    private $curl_signature_method = 'HMAC-SHA1';
    private $curl_action = 'SetBackendServers';


    public function __Construct() {
        $slbConfig = \Yii::$app->params['slb'];
        $this->machines = $slbConfig['machines'];
        $this->slbs = $slbConfig['slbs'];
        $this->accessKey = $slbConfig['accessKey'];
        $this->accessSecret = $slbConfig['accessSecret']; 
        $this->address = $slbConfig['address'];
    }

    /**
     * change the rate of flow for point machines
     * @param  string  $machine the machine to change flow
     * @param  integer $flow    the flow to change
     */
    public function changeSlb($machine, $flow) {
        if (!($serverId = $this->getServerId($machine))) throw new \Exception(\Yii::t('slb', 'not found machine', ['machine' => $machine])); 
        $slbId = $this->getSlbId($machine);
        if ($slbId === null) throw new \Exception(\Yii::t('slb', 'not found slb', ['machine' => $machine]));
        if ($slbId === []) throw new \Exception(\Yii::t('slb', 'too many slb', ['machine' => $machine]));
        if (!$this->checkFlow($flow)) throw new \Exception(\Yii::t('slb', 'iwrong flow', ['machine' => $machine]));
        $url = $this->getUrl($serverId, $slbId, $flow);
        $r = $this->execSlbCmd($url);
        if ($r->isOk) return $this->parseResult($r->data); 
        else throw new \Exception(\Yii::t('slb', 'query aliyun error', ['result' => json_encode($r->data)]));
    }

    private function getServerId($machine) {
        if (!isset($this->machines[$machine])) return false;
        return $this->machines[$machine];
    }

    private function getSlbId($machine) {
        $slbId = null;
        foreach($this->slbs as $slb) {
            if (in_array($machine, $slb['servers'])) {
                if ($slbId === null) $slbId = $slb['key'];
                else $slbId = [];
            }
        }
        return $slbId;
    }

    private function checkFlow($flow) {
        if (is_int($flow) && $flow >= 0 && $flow <= 100) return true;
        return false;
    }

    private function getUuid() {
        mt_srand((double)microtime()*10000);
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12);
        return $uuid;
    }

    private function percentEncode($str) {
        $str = urlencode($str);
        $str = str_replace('+', '%20', $str);
        $str = str_replace('*', '%2A', $str);
        $str = str_replace('%7E', '~', $str);
        return $str;
    }

    private function compute_signature($params, $method = 'GET') {
        ksort($params);
        $stringToSign = strtoupper($method).'&'.$this->percentEncode('/').'&';

        $tmp = "";
        foreach($params as $key => $val){
            $tmp .= '&'.$this->percentEncode($key).'='.$this->percentEncode($val);
        }
        $tmp = trim($tmp, '&');
        $stringToSign = $stringToSign.$this->percentEncode($tmp);

        $key  = $this->accessSecret.'&';
        $hmac = hash_hmac("sha1", $stringToSign, $key, true);

        return base64_encode($hmac);
    }

    private function getUrl($serverId, $slbId, $flow) {
        date_default_timezone_set("UTC");
        $timestamp = date('Y-m-d\TH:i:s\Z', time());
        date_default_timezone_set("Asia/Shanghai");
        $backendServers = json_encode([[
            'ServerId' => $serverId,
            'Weight' => $flow
        ]]);
        $params = [
            'Format' => $this->curl_format,
            'Version' => $this->curl_version,
            'AccessKeyId' => $this->accessKey,
            'SignatureVersion' => $this->curl_signature_version,
            'SignatureMethod' => $this->curl_signature_method,
            'Action' => $this->curl_action,
            'BackendServers' => $backendServers,
            'LoadBalancerId' => $slbId,
            'SignatureNonce' => $this->getUuid(),
            'TimeStamp' => $timestamp
        ];
        $signature = $this->compute_signature($params);
        $params['Signature'] = $signature;
        $url = $this->address . '/?' . http_build_query($params);
        return $url;
    }

    private function execSlbCmd($url) {
        $client  =  new Client();
        $response = $client->createRequest()
            ->setMethod('get')
            ->setUrl($url)
            ->send();
        return $response;
    }

    private function parseResult($data) {
        $data = json_decode($data, true);
        if (isset($data['BackendServers']) && isset($data['BackendServers']['BackendServer'])) {
            return ''; 
        }
        return '';
    }
}
