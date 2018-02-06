<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 2/5/18
 * Time: 8:57 PM
 */

namespace app\console;


use yii\console\Controller;

class MailerController extends Controller
{
    /**
     * This command echoes what you have entered as the message.
     * @param string $message the message to be echoed.
     */
    public function actionSend()
    {
        \Yii::$app->mail->process();
    }
}