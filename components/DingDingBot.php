<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 1/30/18
 * Time: 4:54 PM
 */

namespace app\components;

define('DINGDING_BOT_ACCESSTOKEN', '1b0e5803d597d0a35018f6faf9b246b0a43361606a22071526eb6153bc7bcd3f');

class DingDingBot
{
    private $token;
    private $url;

    public function __construct($token = false)
    {
        if (!$token) $token = DINGDING_BOT_ACCESSTOKEN;
        $this->token = $token;
        $this->url = "https://oapi.dingtalk.com/robot/send?access_token=$this->token";
    }

    public function sendToAll($text)
    {
        $content = json_encode(array(
            'msgtype' => "text",
            'text' => array(
                'content' => $text,
            ),
            'at' => array(
                'isAtAll' => true
            )
        ));
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        @ $output = curl_exec($ch);
        curl_close($ch);
        Command::log("[DINGDING] request($content) response($output)");
    }
}

