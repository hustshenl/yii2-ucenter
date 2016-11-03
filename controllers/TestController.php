<?php
namespace hustshenl\ucenter\controllers;

use hustshenl\ucenter\components\Client;
use hustshenl\ucenter\components\Xml;
use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use hustshenl\ucenter\components\UcReceiver;

/**
 * Site controller
 * @property \hustshenl\ucenter\Module $module
 */
class TestController extends Controller
{
    public $enableCsrfValidation = FALSE;

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIndex()
    {
        $client = new Client();
        $user = $client->user_login('abc4','123456','abc4@123.com');
        return var_dump($user);
    }

}

