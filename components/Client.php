<?php
/**
 * @Author shen@shenl.com
 * @Create Time: 2015/5/28 11:04
 * @Description: 功能包含：会员注册，会员登陆，会员头像，修改密码
 */

namespace hustshenl\ucenter\components;

use hustshenl\ucenter\Module;
use yii\base\Component;
use yii;

/**
 * Class Client
 * @property \hustshenl\ucenter\Module $module
 * @property string $ucApiFunc
 * @property string $clientRoot
 * @package hustshenl\ucenter\components
 */
class Client extends Component
{
    const UC_CLIENT_VERSION = '1.6.0';
    const UC_CLIENT_RELEASE = '20141101';

    public $clientBase = '\hustshenl\ucenter\components\client\controllers\\';

    private $_module = false;
    private $_ucApiFunc = false;
    private $_clientRoot = false;

    public function init()
    {
        parent::init();
        \Yii::trace(Module::getInstance()->uc_api);
    }
    public function getModule()
    {
        if($this->_module === false) $this->_module = Module::getInstance();
        return $this->_module;
    }
    public function getUcApiFunc()
    {
        if($this->_ucApiFunc === false) $this->_ucApiFunc = $this->module->uc_connect == 'mysql' ? 'api_mysql' : 'api_post';
        return $this->_ucApiFunc;
    }
    public function getUcRoot()
    {
        if($this->_clientRoot === false) $this->_clientRoot = \Yii::getAlias('@hustshenl/ucenter/client');
        return $this->_clientRoot;
    }

    /**
     * @param $model
     * @return mixed
     */
    public function getModelName($model)
    {
        return $this->clientBase.ucfirst($model).'Controller';
    }
    public static function addslashes($string, $force = 0, $strip = FALSE)
    {
        !defined('MAGIC_QUOTES_GPC') && define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc());
        if (!MAGIC_QUOTES_GPC || $force) {
            if (is_array($string)) {
                foreach ($string as $key => $val) {
                    $string[$key] = self::addslashes($val, $force, $strip);
                }
            } else {
                $string = addslashes($strip ? stripslashes($string) : $string);
            }
        }
        return $string;
    }

    function stripslashes($string)
    {
        !defined('MAGIC_QUOTES_GPC') && define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc());
        if (MAGIC_QUOTES_GPC) {
            return stripslashes($string);
        } else {
            return $string;
        }
    }

    function api_post($module, $action, $arg = array())
    {
        $s = $sep = '';
        foreach ($arg as $k => $v) {
            $k = urlencode($k);
            if (is_array($v)) {
                $s2 = $sep2 = '';
                foreach ($v as $k2 => $v2) {
                    $k2 = urlencode($k2);
                    $s2 .= "$sep2{$k}[$k2]=" . urlencode($this->stripslashes($v2));
                    $sep2 = '&';
                }
                $s .= $sep . $s2;
            } else {
                $s .= "$sep$k=" . urlencode($this->stripslashes($v));
            }
            $sep = '&';
        }
        $postdata = $this->api_requestdata($module, $action, $s);
        return $this->fopen2($this->module->uc_api . '/index.php', 500000, $postdata, '', TRUE, $this->module->uc_ip, 20);
    }

    function api_requestdata($module, $action, $arg = '', $extra = '')
    {
        $input = $this->api_input($arg);
        $post = "m=$module&a=$action&inajax=2&release=" . self::UC_CLIENT_RELEASE . "&input=$input&appid=" . $this->module->uc_appid . $extra;
        return $post;
    }

    function api_url($module, $action, $arg = '', $extra = '')
    {
        $url = $this->module->uc_api . '/index.php?' . $this->api_requestdata($module, $action, $arg, $extra);
        return $url;
    }

    function api_input($data)
    {
        $s = urlencode($this->authcode($data . '&agent=' . md5($_SERVER['HTTP_USER_AGENT']) . "&time=" . time(), 'ENCODE', $this->module->uc_key));
        return $s;
    }

    function api_mysql($model, $action, $args = array())
    {
        $modelName = $this->getModelName($model);
        $client = new $modelName();
        if ($action{0} != '_') {
            $client->args = $this->addslashes($args, 1, TRUE);
            return $client->$action($args);
        }
        $client->$action($args);
        return;
        global $uc_controls;
        if (empty($uc_controls[$model])) {
            if (function_exists("mysql_connect")) {
                include_once $this->clientRoot . './lib/db.class.php';
            } else {
                include_once $this->clientRoot . './lib/dbi.class.php';
            }
            include_once $this->clientRoot . './model/base.php';
            include_once $this->clientRoot . "./control/$model.php";
            eval("\$uc_controls['$model'] = new {$model}control();");
        }
        if ($action{0} != '_') {
            $args = $this->addslashes($args, 1, TRUE);
            $action = 'on' . $action;
            $uc_controls[$model]->input = $args;
            return $uc_controls[$model]->$action($args);
        } else {
            return '';
        }
    }

    function serialize($arr, $htmlon = 0)
    {
        return XML::xml_serialize($arr, $htmlon);
    }

    function unserialize($s)
    {
        return XML::xml_unserialize($s);
    }

    function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0)
    {

        $ckey_length = 4;

        $key = md5($key ? $key : $this->module->uc_key);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);

        $result = '';
        $box = range(0, 255);

        $rndkey = array();
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if ($operation == 'DECODE') {
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc . str_replace('=', '', base64_encode($result));
        }
    }

    function fopen2($url, $limit = 0, $post = '', $cookie = '', $bysocket = FALSE, $ip = '', $timeout = 15, $block = TRUE)
    {
        $__times__ = isset($_GET['__times__']) ? intval($_GET['__times__']) + 1 : 1;
        if ($__times__ > 2) {
            return '';
        }
        $url .= (strpos($url, '?') === FALSE ? '?' : '&') . "__times__=$__times__";
        return $this->fopen($url, $limit, $post, $cookie, $bysocket, $ip, $timeout, $block);
    }

    function fopen($url, $limit = 0, $post = '', $cookie = '', $bysocket = FALSE, $ip = '', $timeout = 15, $block = TRUE)
    {
        $return = '';
        $matches = parse_url($url);
        !isset($matches['scheme']) && $matches['scheme'] = '';
        !isset($matches['host']) && $matches['host'] = '';
        !isset($matches['path']) && $matches['path'] = '';
        !isset($matches['query']) && $matches['query'] = '';
        !isset($matches['port']) && $matches['port'] = '';
        $scheme = $matches['scheme'];
        $host = $matches['host'];
        $path = $matches['path'] ? $matches['path'] . ($matches['query'] ? '?' . $matches['query'] : '') : '/';
        $port = !empty($matches['port']) ? $matches['port'] : 80;
        if ($post) {
            $out = "POST $path HTTP/1.0\r\n";
            $header = "Accept: */*\r\n";
            $header .= "Accept-Language: zh-cn\r\n";
            $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $header .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
            $header .= "Host: $host\r\n";
            $header .= 'Content-Length: ' . strlen($post) . "\r\n";
            $header .= "Connection: Close\r\n";
            $header .= "Cache-Control: no-cache\r\n";
            $header .= "Cookie: $cookie\r\n\r\n";
            $out .= $header . $post;
        } else {
            $out = "GET $path HTTP/1.0\r\n";
            $header = "Accept: */*\r\n";
            $header .= "Accept-Language: zh-cn\r\n";
            $header .= "User-Agent: $_SERVER[HTTP_USER_AGENT]\r\n";
            $header .= "Host: $host\r\n";
            $header .= "Connection: Close\r\n";
            $header .= "Cookie: $cookie\r\n\r\n";
            $out .= $header;
        }

        $fpflag = 0;
        if (!$fp = @fsocketopen(($ip ? $ip : $host), $port, $errno, $errstr, $timeout)) {
            $context = array(
                'http' => array(
                    'method' => $post ? 'POST' : 'GET',
                    'header' => $header,
                    'content' => $post,
                    'timeout' => $timeout,
                ),
            );
            $context = stream_context_create($context);
            $fp = @fopen($scheme . '://' . ($ip ? $ip : $host) . ':' . $port . $path, 'b', false, $context);
            $fpflag = 1;
        }

        if (!$fp) {
            return '';
        } else {
            stream_set_blocking($fp, $block);
            stream_set_timeout($fp, $timeout);
            @fwrite($fp, $out);
            $status = stream_get_meta_data($fp);
            if (!$status['timed_out']) {
                while (!feof($fp) && !$fpflag) {
                    if (($header = @fgets($fp)) && ($header == "\r\n" || $header == "\n")) {
                        break;
                    }
                }

                $stop = false;
                while (!feof($fp) && !$stop) {
                    $data = fread($fp, ($limit == 0 || $limit > 8192 ? 8192 : $limit));
                    $return .= $data;
                    if ($limit) {
                        $limit -= strlen($data);
                        $stop = $limit <= 0;
                    }
                }
            }
            @fclose($fp);
            return $return;
        }
    }

    function app_ls()
    {
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'app', 'ls', array());
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function feed_add($icon, $uid, $username, $title_template = '', $title_data = '', $body_template = '', $body_data = '', $body_general = '', $target_ids = '', $images = array())
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'feed', 'add',
            array('icon' => $icon,
                'appid' => $this->module->uc_appid,
                'uid' => $uid,
                'username' => $username,
                'title_template' => $title_template,
                'title_data' => $title_data,
                'body_template' => $body_template,
                'body_data' => $body_data,
                'body_general' => $body_general,
                'target_ids' => $target_ids,
                'image_1' => $images[0]['url'],
                'image_1_link' => $images[0]['link'],
                'image_2' => $images[1]['url'],
                'image_2_link' => $images[1]['link'],
                'image_3' => $images[2]['url'],
                'image_3_link' => $images[2]['link'],
                'image_4' => $images[3]['url'],
                'image_4_link' => $images[3]['link']
            )
        );
    }

    function feed_get($limit = 100, $delete = TRUE)
    {
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'feed', 'get', array('limit' => $limit, 'delete' => $delete));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function friend_add($uid, $friendid, $comment = '')
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'friend', 'add', array('uid' => $uid, 'friendid' => $friendid, 'comment' => $comment));
    }

    function friend_delete($uid, $friendids)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'friend', 'delete', array('uid' => $uid, 'friendids' => $friendids));
    }

    function friend_totalnum($uid, $direction = 0)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'friend', 'totalnum', array('uid' => $uid, 'direction' => $direction));
    }

    function friend_ls($uid, $page = 1, $pagesize = 10, $totalnum = 10, $direction = 0)
    {
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'friend', 'ls', array('uid' => $uid, 'page' => $page, 'pagesize' => $pagesize, 'totalnum' => $totalnum, 'direction' => $direction));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function user_register($username, $password, $email, $questionid = '', $answer = '', $regip = '')
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'register', array('username' => $username, 'password' => $password, 'email' => $email, 'questionid' => $questionid, 'answer' => $answer, 'regip' => $regip));
    }

    function user_login($username, $password, $isuid = 0, $checkques = 0, $questionid = '', $answer = '', $ip = '')
    {
        $isuid = intval($isuid);
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'user', 'login', array('username' => $username, 'password' => $password, 'isuid' => $isuid, 'checkques' => $checkques, 'questionid' => $questionid, 'answer' => $answer, 'ip' => $ip));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function user_synlogin($uid)
    {
        $uid = intval($uid);
        $cache = Yii::$app->cache->get(UcReceiver::APPS_TAG);
        if (count($cache) > 1) {
            $return = $this->api_post('user', 'synlogin', array('uid' => $uid));
        } else {
            $return = '';
        }
        return $return;
    }

    function user_synlogout()
    {
        $cache = Yii::$app->cache->get(UcReceiver::APPS_TAG);
        if (count($cache) > 1) {
            $return = $this->api_post('user', 'synlogout', array());
        } else {
            $return = '';
        }
        return $return;
    }

    function user_edit($username, $oldpw, $newpw, $email, $ignoreoldpw = 0, $questionid = '', $answer = '')
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'edit', array('username' => $username, 'oldpw' => $oldpw, 'newpw' => $newpw, 'email' => $email, 'ignoreoldpw' => $ignoreoldpw, 'questionid' => $questionid, 'answer' => $answer));
    }

    function user_delete($uid)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'delete', array('uid' => $uid));
    }

    function user_deleteavatar($uid)
    {
        $this->api_post('user', 'deleteavatar', array('uid' => $uid));
    }

    function user_checkname($username)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'check_username', array('username' => $username));
    }

    function user_checkemail($email)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'check_email', array('email' => $email));
    }

    function user_addprotected($username, $admin = '')
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'addprotected', array('username' => $username, 'admin' => $admin));
    }

    function user_deleteprotected($username)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'deleteprotected', array('username' => $username));
    }

    function user_getprotected()
    {
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'user', 'getprotected', array('1' => 1));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function get_user($username, $isuid = 0)
    {
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'user', 'get_user', array('username' => $username, 'isuid' => $isuid));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function user_merge($oldusername, $newusername, $uid, $password, $email)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'merge', array('oldusername' => $oldusername, 'newusername' => $newusername, 'uid' => $uid, 'password' => $password, 'email' => $email));
    }

    function user_merge_remove($username)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'merge_remove', array('username' => $username));
    }

    function user_getcredit($appid, $uid, $credit)
    {
        return $this->api_post('user', 'getcredit', array('appid' => $appid, 'uid' => $uid, 'credit' => $credit));
    }


    function user_logincheck($username, $ip)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'user', 'logincheck', array('username' => $username, 'ip' => $ip));
    }

    function pm_location($uid, $newpm = 0)
    {
        $apiurl = $this->api_url('pm_client', 'ls', "uid=$uid", ($newpm ? '&folder=newbox' : ''));
        @header("Expires: 0");
        @header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
        @header("Pragma: no-cache");
        @header("location: $apiurl");
    }

    function pm_checknew($uid, $more = 0)
    {
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'check_newpm', array('uid' => $uid, 'more' => $more));
        return (!$more || $this->module->uc_connect == 'mysql') ? $return : $this->unserialize($return);
    }

    function pm_send($fromuid, $msgto, $subject, $message, $instantly = 1, $replypmid = 0, $isusername = 0, $type = 0)
    {
        if ($instantly) {
            $replypmid = @is_numeric($replypmid) ? $replypmid : 0;
            return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'sendpm', array('fromuid' => $fromuid, 'msgto' => $msgto, 'subject' => $subject, 'message' => $message, 'replypmid' => $replypmid, 'isusername' => $isusername, 'type' => $type));
        } else {
            $fromuid = intval($fromuid);
            $subject = rawurlencode($subject);
            $msgto = rawurlencode($msgto);
            $message = rawurlencode($message);
            $replypmid = @is_numeric($replypmid) ? $replypmid : 0;
            $replyadd = $replypmid ? "&pmid=$replypmid&do=reply" : '';
            $apiurl = $this->api_url('pm_client', 'send', "uid=$fromuid", "&msgto=$msgto&subject=$subject&message=$message$replyadd");
            @header("Expires: 0");
            @header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", FALSE);
            @header("Pragma: no-cache");
            @header("location: " . $apiurl);
        }
    }

    function pm_delete($uid, $folder, $pmids)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'delete', array('uid' => $uid, 'pmids' => $pmids));
    }

    function pm_deleteuser($uid, $touids)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'deleteuser', array('uid' => $uid, 'touids' => $touids));
    }

    function pm_deletechat($uid, $plids, $type = 0)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'deletechat', array('uid' => $uid, 'plids' => $plids, 'type' => $type));
    }

    function pm_readstatus($uid, $uids, $plids = array(), $status = 0)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'readstatus', array('uid' => $uid, 'uids' => $uids, 'plids' => $plids, 'status' => $status));
    }

    function pm_list($uid, $page = 1, $pagesize = 10, $folder = 'inbox', $filter = 'newpm', $msglen = 0)
    {
        $uid = intval($uid);
        $page = intval($page);
        $pagesize = intval($pagesize);
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'ls', array('uid' => $uid, 'page' => $page, 'pagesize' => $pagesize, 'filter' => $filter, 'msglen' => $msglen));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function pm_ignore($uid)
    {
        $uid = intval($uid);
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'ignore', array('uid' => $uid));
    }

    function pm_view($uid, $pmid = 0, $touid = 0, $daterange = 1, $page = 0, $pagesize = 10, $type = 0, $isplid = 0)
    {
        $uid = intval($uid);
        $touid = intval($touid);
        $page = intval($page);
        $pagesize = intval($pagesize);
        $pmid = @is_numeric($pmid) ? $pmid : 0;
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'view', array('uid' => $uid, 'pmid' => $pmid, 'touid' => $touid, 'daterange' => $daterange, 'page' => $page, 'pagesize' => $pagesize, 'type' => $type, 'isplid' => $isplid));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function pm_view_num($uid, $touid, $isplid)
    {
        $uid = intval($uid);
        $touid = intval($touid);
        $isplid = intval($isplid);
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'viewnum', array('uid' => $uid, 'touid' => $touid, 'isplid' => $isplid));
    }

    function pm_viewnode($uid, $type, $pmid)
    {
        $uid = intval($uid);
        $type = intval($type);
        $pmid = @is_numeric($pmid) ? $pmid : 0;
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'viewnode', array('uid' => $uid, 'type' => $type, 'pmid' => $pmid));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function pm_chatpmmemberlist($uid, $plid = 0)
    {
        $uid = intval($uid);
        $plid = intval($plid);
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'chatpmmemberlist', array('uid' => $uid, 'plid' => $plid));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function pm_kickchatpm($plid, $uid, $touid)
    {
        $uid = intval($uid);
        $plid = intval($plid);
        $touid = intval($touid);
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'kickchatpm', array('uid' => $uid, 'plid' => $plid, 'touid' => $touid));
    }

    function pm_appendchatpm($plid, $uid, $touid)
    {
        $uid = intval($uid);
        $plid = intval($plid);
        $touid = intval($touid);
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'appendchatpm', array('uid' => $uid, 'plid' => $plid, 'touid' => $touid));
    }

    function pm_blackls_get($uid)
    {
        $uid = intval($uid);
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'blackls_get', array('uid' => $uid));
    }

    function pm_blackls_set($uid, $blackls)
    {
        $uid = intval($uid);
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'blackls_set', array('uid' => $uid, 'blackls' => $blackls));
    }

    function pm_blackls_add($uid, $username)
    {
        $uid = intval($uid);
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'blackls_add', array('uid' => $uid, 'username' => $username));
    }

    function pm_blackls_delete($uid, $username)
    {
        $uid = intval($uid);
        return call_user_func([Client::className(),$this->ucApiFunc], 'pm', 'blackls_delete', array('uid' => $uid, 'username' => $username));
    }

    function domain_ls()
    {
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'domain', 'ls', array('1' => 1));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function credit_exchange_request($uid, $from, $to, $toappid, $amount)
    {
        $uid = intval($uid);
        $from = intval($from);
        $toappid = intval($toappid);
        $to = intval($to);
        $amount = intval($amount);
        return $this->api_post('credit', 'request', array('uid' => $uid, 'from' => $from, 'to' => $to, 'toappid' => $toappid, 'amount' => $amount));
    }

    function tag_get($tagname, $nums = 0)
    {
        $return = call_user_func([Client::className(),$this->ucApiFunc], 'tag', 'gettag', array('tagname' => $tagname, 'nums' => $nums));
        return $this->module->uc_connect == 'mysql' ? $return : $this->unserialize($return);
    }

    function avatar($uid, $type = 'virtual', $returnhtml = 1)
    {
        $uid = intval($uid);
        $uc_input = $this->api_input("uid=$uid");
        $uc_avatarflash = $this->module->uc_api . '/images/camera.swf?inajax=1&appid=' . $this->module->uc_appid . '&input=' . $uc_input . '&agent=' . md5($_SERVER['HTTP_USER_AGENT']) . '&ucapi=' . urlencode(str_replace('http://', '', $this->module->uc_api)) . '&avatartype=' . $type . '&uploadSize=2048';
        if ($returnhtml) {
            return '<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0" width="450" height="253" id="mycamera" align="middle">
			<param name="allowScriptAccess" value="always" />
			<param name="scale" value="exactfit" />
			<param name="wmode" value="transparent" />
			<param name="quality" value="high" />
			<param name="bgcolor" value="#ffffff" />
			<param name="movie" value="' . $uc_avatarflash . '" />
			<param name="menu" value="false" />
			<embed src="' . $uc_avatarflash . '" quality="high" bgcolor="#ffffff" width="450" height="253" name="mycamera" align="middle" allowScriptAccess="always" allowFullScreen="false" scale="exactfit"  wmode="transparent" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" />
		</object>';
        } else {
            return array(
                'width', '450',
                'height', '253',
                'scale', 'exactfit',
                'src', $uc_avatarflash,
                'id', 'mycamera',
                'name', 'mycamera',
                'quality', 'high',
                'bgcolor', '#ffffff',
                'menu', 'false',
                'swLiveConnect', 'true',
                'allowScriptAccess', 'always'
            );
        }
    }

    function mail_queue($uids, $emails, $subject, $message, $frommail = '', $charset = 'gbk', $htmlon = FALSE, $level = 1)
    {
        return call_user_func([Client::className(),$this->ucApiFunc], 'mail', 'add', array('uids' => $uids, 'emails' => $emails, 'subject' => $subject, 'message' => $message, 'frommail' => $frommail, 'charset' => $charset, 'htmlon' => $htmlon, 'level' => $level));
    }

    function check_avatar($uid, $size = 'middle', $type = 'virtual')
    {
        $url = $this->module->uc_api . "/avatar.php?uid=$uid&size=$size&type=$type&check_file_exists=1";
        $res = $this->fopen2($url, 500000, '', '', TRUE, $this->module->uc_ip, 20);
        if ($res == 1) {
            return 1;
        } else {
            return 0;
        }
    }

    function check_version()
    {
        $return = $this->api_post('version', 'check', array());
        $data = $this->unserialize($return);
        return is_array($data) ? $data : $return;
    }
}

if (!function_exists('daddslashes')) {
    function daddslashes($string, $force = 0)
    {
        return Client::addslashes($string, $force);
    }
}


if (!function_exists('dhtmlspecialchars')) {
    function dhtmlspecialchars($string, $flags = null)
    {
        if (is_array($string)) {
            foreach ($string as $key => $val) {
                $string[$key] = dhtmlspecialchars($val, $flags);
            }
        } else {
            if ($flags === null) {
                $string = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $string);
                if (strpos($string, '&amp;#') !== false) {
                    $string = preg_replace('/&amp;((#(\d{3,5}|x[a-fA-F0-9]{4}));)/', '&\\1', $string);
                }
            } else {
                if (PHP_VERSION < '5.4.0') {
                    $string = htmlspecialchars($string, $flags);
                } else {
                    if (strtolower(Module::getInstance()->uc_charset) == 'utf-8') {
                        $charset = 'UTF-8';
                    } else {
                        $charset = 'ISO-8859-1';
                    }
                    $string = htmlspecialchars($string, $flags, $charset);
                }
            }
        }
        return $string;
    }
}
if (!function_exists('fsocketopen')) {
    function fsocketopen($hostname, $port = 80, &$errno, &$errstr, $timeout = 15)
    {
        $fp = '';
        if (function_exists('fsockopen')) {
            $fp = @fsockopen($hostname, $port, $errno, $errstr, $timeout);
        } elseif (function_exists('pfsockopen')) {
            $fp = @pfsockopen($hostname, $port, $errno, $errstr, $timeout);
        } elseif (function_exists('stream_socket_client')) {
            $fp = @stream_socket_client($hostname . ':' . $port, $errno, $errstr, $timeout);
        }
        return $fp;
    }
}