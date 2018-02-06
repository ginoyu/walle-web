<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 2/5/18
 * Time: 6:01 PM
 */

namespace app\components;

class Message extends \yii\swiftmailer\Message
{
    public function queue()
    {
        Command::log('pid:' . getmypid() . ' queue message called');
        $redis = new \Redis();
        $conResult = $redis->connect(\Yii::$app->params['redis']['url'], \Yii::$app->params['redis']['port']);
        if (!$conResult) {
            Command::log('redis connect error!');
        }
        if (empty($redis)) {
            throw new \yii\base\InvalidConfigException('redis not found in config.');
        }

        Command::log('pid:' . getmypid() . ' redis success');

        // 0 - 15  select 0 select 1
        // db => 1
        $mailer = \Yii::$app->mail;

        if (empty($mailer) || !$redis->select($mailer->db)) {
            Command::log('pid:' . getmypid() . (empty($mailer) ? ' empty' : ' '));
            throw new \yii\base\InvalidConfigException('db not defined.');
        }

        Command::log('pid:' . getmypid() . ' mailer success');
        $message = [];
        $message['from'] = array_keys($this->getFrom());
        $message['to'] = array_keys($this->getTo());
        if ($this->getCc()) {
            $message['cc'] = array_keys($this->getCc());
        }
        if ($this->getBcc()) {
            $message['bcc'] = array_keys($this->getBcc());
        }
        if ($this->getReplyTo()) {
            $message['reply_to'] = array_keys($this->getReplyTo());
        }
        if ($this->getCharset()) {
            $message['charset'] = array_keys($this->getCharset());
        }
        if ($this->getSubject()) {
            $message['subject'] = $this->getSubject();
        }
        $parts = $this->getSwiftMessage()->getChildren();
        if (!is_array($parts) || !sizeof($parts)) {
            $parts = [$this->getSwiftMessage()];
        }
        foreach ($parts as $part) {
            if (!$part instanceof \Swift_Mime_Attachment) {
                switch ($part->getContentType()) {
                    case 'text/html':
                        $message['html_body'] = $part->getBody();
                        break;
                    case 'text/plain':
                        $message['text_body'] = $part->getBody();
                        break;
                }
                if (!array_key_exists('charset', $message) || !$message['charset']) {
                    $message['charset'] = $part->getCharset();
                }
            }
        }
        Command::log('pid:' . getmypid() . 'message in queue:' . json_encode($message));
        return $redis->rpush($mailer->key, json_encode($message));
    }
}