<?php
/**
 * @Author shen@shenl.com
 * @Create Time: 2015/4/1 15:31
 * @Description:
 */

namespace hustshenl\ucenter\components;

use hustshenl\ucenter\models\Auth;
use hustshenl\ucenter\models\User;
use yii\base\Component;
use yii;


class UcReceiver extends Component
{
    const UC_CLIENT_VERSION = '1.6.0';    //note UCenter 版本标识
    const UC_CLIENT_RELEASE = '20110501';

    const API_DELETE_USER = 1;        //note 用户删除 API 接口开关
    const API_RENAME_USER = 1;        //note 用户改名 API 接口开关
    const API_GET_TAG = 1;        //note 获取标签 API 接口开关
    const API_SYN_LOGIN = 1;        //note 同步登录 API 接口开关
    const API_SYN_LOGOUT = 1;        //note 同步登出 API 接口开关
    const API_UPDATE_PW = 1;        //note 更改用户密码 开关
    const API_UPDATE_BADWORDS = 1;    //note 更新关键字列表 开关
    const API_UPDATE_HOSTS = 1;        //note 更新域名解析缓存 开关
    const API_UPDATE_APPS = 1;        //note 更新应用列表 开关
    const API_UPDATE_CLIENT = 1;        //note 更新客户端缓存 开关
    const API_UPDATE_CREDIT = 1;        //note 更新用户积分 开关
    const API_GET_CREDIT_SETTINGS = 1;    //note 向 UCenter 提供积分设置 开关
    const API_GET_CREDIT = 1;        //note 获取用户的某项积分 开关
    const API_UPDATE_CREDIT_SETTINGS = 1;    //note 更新应用积分设置 开关
    const API_ADD_FEED = 1;    //note 更新应用积分设置 开关

    const API_RETURN_SUCCEED = '1';
    const API_RETURN_FAILED = '-1';
    const API_RETURN_FORBIDDEN = '-2';

    const APPS_TAG = 'hust.shenl.ucenter.apps';
    const CLIENT_TAG = 'hust.shenl.ucenter.client';
    const BAD_WORD_TAG = 'hust.shenl.ucenter.bad.word';
    const HOSTS_TAG = 'hust.shenl.ucenter.hosts';
    const CREDIT_SETTINGS_TAG = 'hust.shenl.ucenter.credit.settings';

    public $get;
    public $post;
    public $db;

    public function __construct($config = [])
    {
        // ... initialization before configuration is applied
        parent::__construct($config);
    }

    public function init()
    {
        parent::init();
        // ... initialization after configuration is applied
        $this->db = Yii::$app->db;
    }

    /*public function behaviors()
    {
        return [
            WeixinBehavior::className(),
        ];
    }*/

    /**
     * @return mixed
     */
    public function response()
    {
        $response = call_user_func([UcReceiver::className(),$this->get['action']]);
        return $response;
    }

    /**
     * 测试消息
     * @return string
     */
    function test() {
        return self::API_RETURN_SUCCEED;
    }

    /**
     * 删除用户
     * @return string
     */
    function deleteuser() {
        if(!self::API_DELETE_USER) {
            return self::API_RETURN_FORBIDDEN;
        }
        //从授权登陆表查找用户，并删除用户
        $ids = str_replace("'", '', stripslashes($this->get['ids']));
        $ids = explode(',', $ids);
        $auth = Auth::find()->where(['in','source_id',$ids])->andWhere(['source'=>1])->select('user_id')->column();
        if(empty($auth)) return self::API_RETURN_SUCCEED;
        if(User::deleteAll(['in','id',$auth]) > 0) return self::API_RETURN_SUCCEED;
        return self::API_RETURN_FAILED;
    }
    function renameuser() {
        if(!self::API_RENAME_USER) {
            return self::API_RETURN_FORBIDDEN;
        }
        $source_id = $this->get['uid'];
        $auth = Auth::findOne(['source_id'=>$source_id]);
        if(empty($auth)) return self::API_RETURN_SUCCEED;
        $tables = [
            'user' => ['id' => 'id', 'name' => 'username'],
        ];
        foreach($tables as $table => $conf) {
            $dbCommand = $this->db->createCommand("UPDATE {{%{$table}}} SET `{$conf['name']}`='{$this->get['newusername']}' WHERE `{$conf['id']}`='{$auth->user_id}'");
            $dbCommand->execute();
        }
        return self::API_RETURN_SUCCEED;
    }
    function updatepw() {
        if(!self::API_UPDATE_PW) {
            return self::API_RETURN_FORBIDDEN;
        }
        $user = User::findOne(['username'=>$this->get['username']]);
        $user->setPassword($this->get['password']);
        $user->generateAuthKey();
        if ($user->save()) {
            return self::API_RETURN_SUCCEED;
        }
        return self::API_RETURN_FAILED;
    }
    function synlogin() {
        if(!self::API_SYN_LOGIN) {
            return self::API_RETURN_FORBIDDEN;
        }
        $user = $this->getUserByUid($this->get['uid']);
        header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
        Yii::$app->user->login($user, 3600 * 24 * 30);
        return '';
    }
    function synlogout() {
        if(!self::API_SYN_LOGOUT) {
            return self::API_RETURN_FORBIDDEN;
        }
        //$user = $this->getUserByUid($this->get['uid']);
        header('P3P: CP="CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR"');
        Yii::$app->user->logout();
        return '';

    }
    function updateapps() {
        if(!self::API_UPDATE_APPS) {
            return self::API_RETURN_FORBIDDEN;
        }
        if(isset($this->post['UC_API'])) unset($this->post['UC_API']);
        Yii::$app->cache->set(self::APPS_TAG,$this->post);
        return self::API_RETURN_SUCCEED;
    }
    function updateclient() {
        if(!self::API_UPDATE_CLIENT) {
            return self::API_RETURN_FORBIDDEN;
        }
        Yii::$app->cache->set(self::CLIENT_TAG,$this->post);
        return self::API_RETURN_SUCCEED;
    }
    function updatebadwords() {
        if(!self::API_UPDATE_BADWORDS) {
            return self::API_RETURN_FORBIDDEN;
        }
        $data = [];
        if(is_array($this->post)) {
            foreach($this->post as $k => $v) {
                $data['findpattern'][$k] = $v['findpattern'];
                $data['replace'][$k] = $v['replacement'];
            }
        }
        Yii::$app->cache->set(self::BAD_WORD_TAG, $data);
        return self::API_RETURN_SUCCEED;
    }
    function updatehosts() {
        if(!self::API_UPDATE_HOSTS) {
            return self::API_RETURN_FORBIDDEN;
        }
        Yii::$app->cache->set(self::HOSTS_TAG,$this->post);
        return self::API_RETURN_SUCCEED;
    }

    /**
     * 当某应用执行了积分兑换请求的接口函数 uc_credit_exchange_request() 后，此接口负责通知被兑换的目的应用程序所需修改的用户积分值。
     * 输入的参数 $get['credit'] 表示积分编号，$get['amount'] 表示积分的增减值，$get['uid'] 表示用户 ID。
     * @return string
     */
    function updatecredit() {
        if(!self::API_UPDATE_CREDIT) {
            return self::API_RETURN_FORBIDDEN;
        }
        $uid = intval($this->get['uid']);
        /**
         * @var $user \hustshenl\ucenter\models\User
         */
        $user = $this->getUserByUid($uid);
        if(empty($user)) {
            return self::API_RETURN_SUCCEED;
        }
        $user->updateCounters(['scores'=>$this->get['amount']]);
        return static::API_RETURN_SUCCEED;
    }

    /**
     * 此接口用于把应用程序中指定用户的积分传递给 UCenter。
     * 输入的参数 $get['uid'] 为用户 ID，$get['credit'] 为积分编号。接口运行完毕输出积分值。
     * @return string
     */
    function getcredit() {
        if(!self::API_GET_CREDIT) {
            return self::API_RETURN_FORBIDDEN;
        }
        $uid = intval($this->get['uid']);
        /**
         * @var $user \hustshenl\ucenter\models\User
         */
        $user = $this->getUserByUid($uid);
        if(empty($user)) {
            return 0;
        }
        return $user->scores;;
        /*$credit = intval($this->get['credit']);
        $_G['uid'] = $_G['member']['uid'] = $uid;
        return getuserprofile('extcredits'.$credit);*/
    }

    /**
     * 此接口负责把应用程序的积分设置传递给 UCenter，以供 UCenter 在积分兑换设置中使用。
     * 此接口无输入参数。输出的数组需经过 uc_serialize 处理。
     * 输出的数组单条结构：
    [
    '1' => ['威望', ''],
    '2' => ['金钱', '枚'],
    ]
     * @return string
     */
    function getcreditsettings() {
        if(!self::API_GET_CREDIT_SETTINGS) {
            return self::API_RETURN_FORBIDDEN;
        }
        return Xml::xml_serialize([1=>['积分','']]);
        $credits = array();
        foreach($_G['setting']['extcredits'] as $id => $extcredits) {
            $credits[$id] = array(strip_tags($extcredits['title']), $extcredits['unit']);
        }

        return $this->_serialize($credits);
    }
    function updatecreditsettings() {
        if(!self::API_UPDATE_CREDIT_SETTINGS) {
            return self::API_RETURN_FORBIDDEN;
        }

        $outextcredits = [];
        if(!isset($this->get['credit'])) return static::API_RETURN_SUCCEED;
        foreach($this->get['credit'] as $appid => $credititems) {
            //Yii::$app->cache->set('test',Yii::$app->controller);
            Yii::trace(Yii::$app->controller);
            /*var_dump($this->get['credit']);
            var_dump($appid);*/
            if($appid == Yii::$app->controller->module->uc_appid) {
                foreach($credititems as $value) {
                    $outextcredits[$value['appiddesc'].'|'.$value['creditdesc']] = array(
                        'appiddesc' => $value['appiddesc'],
                        'creditdesc' => $value['creditdesc'],
                        'creditsrc' => $value['creditsrc'],
                        'title' => $value['title'],
                        'unit' => $value['unit'],
                        'ratiosrc' => $value['ratiosrc'],
                        'ratiodesc' => $value['ratiodesc'],
                        'ratio' => $value['ratio']
                    );
                }
            }
        }
        $tmp = array();
        foreach($outextcredits as $value) {
            $key = $value['appiddesc'].'|'.$value['creditdesc'];
            if(!isset($tmp[$key])) {
                $tmp[$key] = array('title' => $value['title'], 'unit' => $value['unit']);
            }
            $tmp[$key]['ratiosrc'][$value['creditsrc']] = $value['ratiosrc'];
            $tmp[$key]['ratiodesc'][$value['creditsrc']] = $value['ratiodesc'];
            $tmp[$key]['creditsrc'][$value['creditsrc']] = $value['ratio'];
        }
        $outextcredits = $tmp;
        Yii::$app->cache->set(self::CREDIT_SETTINGS_TAG,$outextcredits);
        return self::API_RETURN_SUCCEED;
    }


    function gettag() {
        global $_G;
        if(!self::API_GET_TAG) {
            return self::API_RETURN_FORBIDDEN;
        }
        return $this->_serialize(array($this->get['id'], array()), 1);
    }
    function addfeed() {
        if(!self::API_ADD_FEED) {
            return self::API_RETURN_FORBIDDEN;
        }
        return self::API_RETURN_SUCCEED;
    }

    protected function getUserByUidFromUc()
    {

    }
    protected function getUserByNameFromUc()
    {

    }
    protected function getUserByUid($uid)
    {
        $auth = Auth::findOne(['source_id'=>$uid]);
        if(empty($auth)) return null;
        $user = User::findOne($auth->user_id);
        return $user;
    }
}