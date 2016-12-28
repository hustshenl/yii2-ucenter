<?php

namespace hustshenl\ucenter;

use Yii;
use yii\helpers\Inflector;


class Module extends \yii\base\Module
{
    public $uc_connect = 'mysql';   // 连接 UCenter 的方式: mysql/NULL, 默认为空时为 fscoketopen()
                                                                // mysql 是直接连接的数据库, 为了效率, 建议采用 mysql

    //数据库相关 (mysql 连接时, 并且没有设置 UC_DBLINK 时, 需要配置以下变量)
    public $uc_db;
    /*[
    //'class' => 'yii\db\Connection',
    //'dsn' => 'mysql:host=localhost;dbname=sinmh',
    'host' => 'localhost',
    'dbname' => 'sinmh'
    'username' => 'sinmh',
    'password' => 'QUJEqBtAeAnK8uEV',
    'charset' => 'utf8',
    'tablePrefix' => 'pre_',
    ],*/


    //通信相关
    public $uc_key;// 与 UCenter 的通信密钥, 要与 UCenter 保持一致
    public $uc_api;// UCenter 的 URL 地址, 在调用头像时依赖此常量
    public $uc_charset;// UCenter 的字符集
    public $uc_ip;// UCenter 的 IP, 当 UC_CONNECT 为非 mysql 方式时, 并且当前应用服务器解析域名有问题时, 请设置此值
    public $uc_appid;// 当前应用的 ID



    /**
     * @var string Main layout using for module. Default to layout of parent module.
     * Its used when `layout` set to 'left-menu', 'right-menu' or 'top-menu'.
     */
    public $mainLayout = '@hustshenl/ucenter/views/layouts/main.php';

    public $cachePath = '@runtime/ucenter';
    public $caheDuration = 86400;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if (!isset(Yii::$app->i18n->translations['hustshenl-ucenter'])) {
            Yii::$app->i18n->translations['hustshenl-ucenter'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'basePath' => '@hustshenl/ucenter/messages',
                'sourceLanguage' => 'en-US',
                'fileMap' => [
                    'hustshenl-ucenter' => 'app.php',
                ],
            ];
        }

        //Yii::$app->cache->cachePath = Yii::getAlias($this->cachePath);
    }


}