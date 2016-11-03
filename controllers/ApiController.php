<?php
namespace hustshenl\ucenter\controllers;

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
class ApiController extends Controller
{
    public $enableCsrfValidation = FALSE;
    /**
     * @inheritdoc
     */
    /*public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout', 'signup'],
                'rules' => [
                    [
                        'actions' => ['signup'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }*/

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
        return $this->render('index');
    }

    public function actionUc($code)
    {
        //$this->enableCsrfValidation = false;
        $get = $post = [];
        defined('MAGIC_QUOTES_GPC') || define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc());
        parse_str(_authcode($code, 'DECODE', $this->module->uc_key), $get);
        //var_dump($get['time']);exit;
        Yii::trace($get);
        if(MAGIC_QUOTES_GPC) {
            $get = _stripslashes($get);
        }

        $timestamp = time();
        /*if($timestamp - $get['time'] > 3600) {
            exit('Authracation has expiried');
        }*/
        if(empty($get)) {
            exit('Invalid Request');
        }
        $postStr = Yii::$app->request->rawBody;
        /*if (get_magic_quotes_runtime())
        {
            $postStr = stripslashes($postStr);
        }*/
        $post = Xml::xml_unserialize($postStr);
        Yii::trace($post);
        if(empty($get['action'])) return 1;
        if(in_array($get['action'], array('test', 'deleteuser', 'renameuser', 'gettag', 'synlogin', 'synlogout', 'updatepw', 'updatebadwords', 'updatehosts', 'updateapps', 'updateclient', 'updatecredit', 'getcreditsettings', 'updatecreditsettings'))) {
            $ucReceiver = new UcReceiver(['get'=>$get,'post'=>$post]);
            return $ucReceiver->response();
        }
        return UcReceiver::API_RETURN_FAILED;
    }
    public function actionUcTest()
    {
        $get = ['action'=>'synlogin','uid'=>10];
        $post = [];
        $ucReceiver = new UcReceiver(['get'=>$get,'post'=>$post]);
        return $ucReceiver->response();
    }
}

function _authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
    $ckey_length = 4;

    $key = md5($key ? $key : UC_KEY);
    $keya = md5(substr($key, 0, 16));
    $keyb = md5(substr($key, 16, 16));
    $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length): substr(md5(microtime()), -$ckey_length)) : '';

    $cryptkey = $keya.md5($keya.$keyc);
    $key_length = strlen($cryptkey);

    $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0).substr(md5($string.$keyb), 0, 16).$string;
    $string_length = strlen($string);

    $result = '';
    $box = range(0, 255);

    $rndkey = array();
    for($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }

    for($j = $i = 0; $i < 256; $i++) {
        $j = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for($a = $j = $i = 0; $i < $string_length; $i++) {
        $a = ($a + 1) % 256;
        $j = ($j + $box[$a]) % 256;
        $tmp = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }

    if($operation == 'DECODE') {
        if((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc.str_replace('=', '', base64_encode($result));
    }

}

function _stripslashes($string) {
    if(is_array($string)) {
        foreach($string as $key => $val) {
            $string[$key] = _stripslashes($val);
        }
    } else {
        $string = stripslashes($string);
    }
    return $string;
}
