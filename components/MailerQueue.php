<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 2/5/18
 * Time: 6:00 PM
 */

namespace app\components;

use yii\swiftmailer\Mailer;


class MailerQueue extends Mailer
{
    public $messageClass = 'app\components\Message';

    public $key = 'mails';

    public $db = '1';

    public function process()
    {
        Command::log('process pid:' . getmypid() . ' called!');
        $redis = new \Redis();
        $conResult = $redis->connect(\Yii::$app->params['redis']['url'], \Yii::$app->params['redis']['port']);
        if (!$conResult) {
            Command::log('redis connect error!');
        }
        if (empty($redis)) {
            throw new \yii\base\InvalidConfigException('redis not found in config.');
        }
        if ($redis->select($this->db) && $messages = $redis->lrange($this->key, 0, -1)) {
            $messageObj = $this->createMessage();
            foreach ($messages as $message) {
                $message = json_decode($message, true);
                if (empty($message) || !$this->setMessage($messageObj, $message)) {
                    throw new \ServerErrorHttpException('message error');
                }
                if ($messageObj->send()) {
                    $redis->lrem($this->key, json_encode($message), -1);
                    Command::log('process pid:' . getmypid() . ' send success! message:' . json_encode($message));
                } else {
                    Command::log('process pid:' . getmypid() . ' send failed!');
                }
            }
            Command::log('process pid:' . getmypid() . ' message in queue:' . json_encode($messages));
        }

        return true;
    }

    public function setMessage($messageObj, $message)
    {
        if (empty($messageObj)) {
            return false;
        }
        if (/*!empty($message['from']) && */!empty($message['to'])) {

            $messageObj->setFrom(\Yii::$app->mail->messageConfig['from']);
            foreach ($message['to'] as $toAddress) {
                Command::log('to address:' . $toAddress);
                $messageObj->setTo($toAddress);
            }
            if (!empty($message['cc'])) {
                $messageObj->setCc($message['cc']);
            }
            if (!empty($message['bcc'])) {
                $messageObj->setBcc($message['bcc']);
            }
            if (!empty($message['reply_to'])) {
                $messageObj->setReplyTo($message['reply_to']);
            }
            if (!empty($message['charset'])) {
                $messageObj->setCharset($message['charset']);
            }
            if (!empty($message['subject'])) {
                $messageObj->setSubject($message['subject']);
            }
            if (!empty($message['html_body'])) {
                $messageObj->setHtmlBody($message['html_body']);
            }
            if (!empty($message['text_body'])) {
                $messageObj->setTextBody($message['text_body']);
            }
            return $messageObj;
        }
        return false;
    }


}