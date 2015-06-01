<?php

namespace hustshenl\ucenter\components\client\models;

use hustshenl\ucenter\behaviors\IPBehavior;
use hustshenl\ucenter\components\client\Setting;
use hustshenl\ucenter\components\UcReceiver;
use hustshenl\ucenter\Module;
use yii\behaviors\TimestampBehavior;
use yii\base\Component;
use yii\db\ActiveRecord;
use yii;


/**
 * Class User
 * @property \hustshenl\ucenter\Module $module
 * @package hustshenl\ucenter\components
 */
class User extends ActiveRecord
{
    const UC_USER_CHECK_USERNAME_FAILED =  -1;
    const UC_USER_USERNAME_BADWORD = -2;
    const UC_USER_USERNAME_EXISTS = -3;
    const UC_USER_EMAIL_FORMAT_ILLEGAL = -4;
    const UC_USER_EMAIL_ACCESS_ILLEGAL = -5;
    const UC_USER_EMAIL_EXISTS = -6;

    public $args = false;
    private $_module = false;

	public function init() {
        parent::init();
	}
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['regdate', 'lastlogintime'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['lastlogintime'],
                ],
            ],
            [
                'class' => IPBehavior::className(),
                'attributes' => [
                    //ActiveRecord::EVENT_BEFORE_INSERT => ['regip', 'lastloginip'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['lastloginip'],
                ],
            ]
        ];
    }
    public static function getDb()
    {
        return Yii::createObject(Module::getInstance()->uc_db);
    }
    public static function tableName()
    {
        return '{{%members}}';
    }
    public function getModule()
    {
        if($this->_module === false) $this->_module = Module::getInstance();
        return $this->_module;
    }
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['username'], 'string', 'max'=>15],
            [['email','password'], 'string', 'max'=>32],
            [['secques'], 'string', 'max'=>8],
            [['regip'], 'string', 'max'=>15],
            ['password', 'string', 'min' => 6],
            ['password', 'default', 'value'=>'123456'],
        ];
    }
    public function load($data, $formName = null)
    {
        parent::load($data, $formName);
        $this->secques = $this->quescrypt($data['questionid'],$data['answer']);
        $this->salt = Yii::$app->security->generateRandomString(6);
        $this->password = $this->passwordCrypt($this->password,$this->salt);
    }


    public function get_user_by_uid($uid) {
        $arr = $this->db->fetch_first("SELECT * FROM ".UC_DBTABLEPRE."members WHERE uid='$uid'");
        return $arr;
    }

    public function get_user_by_username($username) {
        $arr = $this->db->fetch_first("SELECT * FROM ".UC_DBTABLEPRE."members WHERE username='$username'");
        return $arr;
    }

    public function get_user_by_email($email) {
        $arr = $this->db->fetch_first("SELECT * FROM ".UC_DBTABLEPRE."members WHERE email='$email'");
        return $arr;
    }





    public function check_mergeuser($username) {
        $data = $this->db->result_first("SELECT count(*) FROM ".UC_DBTABLEPRE."mergemembers WHERE appid='".$this->base->app['appid']."' AND username='$username'");
        return $data;
    }





    public function check_login($username, $password, &$user) {
        $user = $this->get_user_by_username($username);
        if(empty($user['username'])) {
            return -1;
        } elseif($user['password'] != md5(md5($password).$user['salt'])) {
            return -2;
        }
        return $user['uid'];
    }



    public function delete_user($uidsarr) {
        $uidsarr = (array)$uidsarr;
        if(!$uidsarr) {
            return 0;
        }
        $uids = $this->base->implode($uidsarr);
        $arr = $this->db->fetch_all("SELECT uid FROM ".UC_DBTABLEPRE."protectedmembers WHERE uid IN ($uids)");
        $puids = array();
        foreach((array)$arr as $member) {
            $puids[] = $member['uid'];
        }
        $uids = $this->base->implode(array_diff($uidsarr, $puids));
        if($uids) {
            $this->db->query("DELETE FROM ".UC_DBTABLEPRE."members WHERE uid IN($uids)");
            $this->db->query("DELETE FROM ".UC_DBTABLEPRE."memberfields WHERE uid IN($uids)");
            uc_user_deleteavatar($uidsarr);
            $this->base->load('note');
            $_ENV['note']->add('deleteuser', "ids=$uids");
            return $this->db->affected_rows();
        } else {
            return 0;
        }
    }

    public function get_total_num($sqladd = '') {
        $data = $this->db->result_first("SELECT COUNT(*) FROM ".UC_DBTABLEPRE."members $sqladd");
        return $data;
    }

    public function get_list($page, $ppp, $totalnum, $sqladd) {
        $start = $this->base->page_get_start($page, $ppp, $totalnum);
        $data = $this->db->fetch_all("SELECT * FROM ".UC_DBTABLEPRE."members $sqladd LIMIT $start, $ppp");
        return $data;
    }

    public function name2id($usernamesarr) {
        $usernamesarr = uc_addslashes($usernamesarr, 1, TRUE);
        $usernames = $this->base->implode($usernamesarr);
        $query = $this->db->query("SELECT uid FROM ".UC_DBTABLEPRE."members WHERE username IN($usernames)");
        $arr = array();
        while($user = $this->db->fetch_array($query)) {
            $arr[] = $user['uid'];
        }
        return $arr;
    }

    public function id2name($uidarr) {
        $arr = array();
        $query = $this->db->query("SELECT uid, username FROM ".UC_DBTABLEPRE."members WHERE uid IN (".$this->base->implode($uidarr).")");
        while($user = $this->db->fetch_array($query)) {
            $arr[$user['uid']] = $user['username'];
        }
        return $arr;
    }

    public function can_do_login($username, $ip = '') {

        $check_times = $this->base->settings['login_failedtime'] < 1 ? 5 : $this->base->settings['login_failedtime'];

        $username = substr(md5($username), 8, 15);
        $expire = 15 * 60;
        if(!$ip) {
            $ip = $this->base->onlineip;
        }

        $ip_check = $user_check = array();
        $query = $this->db->query("SELECT * FROM ".UC_DBTABLEPRE."failedlogins WHERE ip='".$ip."' OR ip='$username'");
        while($row = $this->db->fetch_array($query)) {
            if($row['ip'] === $username) {
                $user_check = $row;
            } elseif($row['ip'] === $ip) {
                $ip_check = $row;
            }
        }

        if(empty($ip_check) || ($this->base->time - $ip_check['lastupdate'] > $expire)) {
            $ip_check = array();
            $this->db->query("REPLACE INTO ".UC_DBTABLEPRE."failedlogins (ip, count, lastupdate) VALUES ('{$ip}', '0', '{$this->base->time}')");
        }

        if(empty($user_check) || ($this->base->time - $user_check['lastupdate'] > $expire)) {
            $user_check = array();
            $this->db->query("REPLACE INTO ".UC_DBTABLEPRE."failedlogins (ip, count, lastupdate) VALUES ('{$username}', '0', '{$this->base->time}')");
        }

        if ($ip_check || $user_check) {
            $time_left = min(($check_times - $ip_check['count']), ($check_times - $user_check['count']));
            return $time_left;

        }

        $this->db->query("DELETE FROM ".UC_DBTABLEPRE."failedlogins WHERE lastupdate<".($this->base->time - ($expire + 1)), 'UNBUFFERED');

        return $check_times;
    }

    public function loginfailed($username, $ip = '') {
        $username = substr(md5($username), 8, 15);
        if(!$ip) {
            $ip = $this->base->onlineip;
        }
        $this->db->query("UPDATE ".UC_DBTABLEPRE."failedlogins SET count=count+1, lastupdate='".$this->base->time."' WHERE ip='".$ip."' OR ip='$username'");
    }


    /**
     * 以下为自定义方法
     */

    /**
     * @param $password
     * @return bool
     */
    public function validatePassword($password)
    {
        $password_md5 = preg_match('/^\w{32}$/', $password) ? $password : md5($password);
        return $this->password == md5($password_md5.$this->salt);
    }

    public function validateSecques($questionid,$answer)
    {
        return $this->secques == $this->quescrypt($questionid,$answer);
    }

    public function quescrypt($questionid,$answer)
    {
        return $questionid > 0 && $answer != '' ? substr(md5($answer.md5($questionid)), 16, 8) : '';
    }

    public function passwordCrypt($password,$salt)
    {
        $password_md5 = preg_match('/^\w{32}$/', $password) ? $password : md5($password);
        return md5($password_md5.$salt);
    }

    public function validateUsername() {
        $username = $this->username;
        if(!$this->check_username()) {
            return self::UC_USER_CHECK_USERNAME_FAILED;
        } elseif(!$this->check_usernamecensor($username)) {
            return self::UC_USER_USERNAME_BADWORD;
        } elseif($this->check_usernameexists($username)) {
            return self::UC_USER_USERNAME_EXISTS;
        }
        return 1;

    }

    public function check_username()
    {
        $username = $this->username;
        $guestexp = '\xA1\xA1|\xAC\xA3|^Guest|^\xD3\xCE\xBF\xCD|\xB9\x43\xAB\xC8';
        $len = self::dstrlen($username);
        if($len > 15 || $len < 3 || preg_match("/\s+|^c:\\con\\con|[%,\*\"\s\<\>\&]|$guestexp/is", $username)) {
            return false;
        } else {
            return true;
        }
    }

    public static function check_usernamecensor($username) {
        $badWord = Yii::$app->cache->get(UcReceiver::BAD_WORD_TAG);
        $setting = new Setting();
        $censorusername = $setting->get('censorusername');
        //$censorusername = $censorusername['censorusername'];
        $censorexp = '/^('.str_replace(array('\\*', "\r\n", ' '), array('.*', '|', ''), preg_quote(($censorusername = trim($censorusername)), '/')).')$/i';
        $usernamereplaced = isset($badWord['findpattern']) && !empty($badWord['findpattern']) ? @preg_replace($badWord['findpattern'], $badWord['replace'], $username) : $username;
        if(($usernamereplaced != $username) || ($censorusername && preg_match($censorexp, $username))) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public function check_usernameexists($username) {
        $user = User::findOne(['username'=>$username]);
        if(empty($user)) return false;
        return $user;
    }

    public function validateEmail() {
        $setting = new Setting();
        $doublee = $setting->get('doublee');
        if(!$this->check_emailformat($this->email)) {
            return self::UC_USER_EMAIL_FORMAT_ILLEGAL;
        } elseif(!$this->check_emailaccess($this->email)) {
            return self::UC_USER_EMAIL_ACCESS_ILLEGAL;
        } elseif(!$doublee && $this->check_emailexists($this->email, $this->username)) {
            return self::UC_USER_EMAIL_EXISTS;
        }
        return 1;
    }

    public static function check_emailformat($email) {
        return strlen($email) > 6 && strlen($email) <= 32 && preg_match("/^([a-z0-9\-_.+]+)@([a-z0-9\-]+[.][a-z0-9\-.]+)$/", $email);
    }

    public function check_emailaccess($email) {
        $setting = new Setting();
        //h$setting = $this->base->get_setting(array('accessemail', 'censoremail'));
        $accessemail = $setting->get('accessemail');
        $censoremail = $setting->get('censoremail');
        $accessexp = '/('.str_replace("\r\n", '|', preg_quote(trim($accessemail), '/')).')$/i';
        $censorexp = '/('.str_replace("\r\n", '|', preg_quote(trim($censoremail), '/')).')$/i';
        if($accessemail || $censoremail) {
            if(($accessemail && !preg_match($accessexp, $email)) || ($censoremail && preg_match($censorexp, $email))) {
                return FALSE;
            } else {
                return TRUE;
            }
        } else {
            return TRUE;
        }
    }

    public static function check_emailexists($email, $username = '') {
        $user = User::find()->where(['<>','username',$username])->andWhere(['email'=>$email])->one();
        if(empty($user)) return false;
        return $user;
    }


    public function edit_user($username, $oldpw, $newpw, $email, $ignoreoldpw = 0, $questionid = '', $answer = '') {
        $data = $this->db->fetch_first("SELECT username, uid, password, salt FROM ".UC_DBTABLEPRE."members WHERE username='$username'");

        if($ignoreoldpw) {
            $isprotected = $this->db->result_first("SELECT COUNT(*) FROM ".UC_DBTABLEPRE."protectedmembers WHERE uid = '$data[uid]'");
            if($isprotected) {
                return -8;
            }
        }

        if(!$ignoreoldpw && $data['password'] != md5(md5($oldpw).$data['salt'])) {
            return -1;
        }

        $sqladd = $newpw ? "password='".md5(md5($newpw).$data['salt'])."'" : '';
        $sqladd .= $email ? ($sqladd ? ',' : '')." email='$email'" : '';
        if($questionid !== '') {
            if($questionid > 0) {
                $sqladd .= ($sqladd ? ',' : '')." secques='".$this->quescrypt($questionid, $answer)."'";
            } else {
                $sqladd .= ($sqladd ? ',' : '')." secques=''";
            }
        }
        if($sqladd || $emailadd) {
            $this->db->query("UPDATE ".UC_DBTABLEPRE."members SET $sqladd WHERE username='$username'");
            return $this->db->affected_rows();
        } else {
            return -7;
        }
    }
    public static function dstrlen($str) {
        if(strtolower(Module::getInstance()->uc_charset) != 'utf-8') {
            return strlen($str);
        }
        $count = 0;
        for($i = 0; $i < strlen($str); $i++){
            $value = ord($str[$i]);
            if($value > 127) {
                $count++;
                if($value >= 192 && $value <= 223) $i++;
                elseif($value >= 224 && $value <= 239) $i = $i + 2;
                elseif($value >= 240 && $value <= 247) $i = $i + 3;
            }
            $count++;
        }
        return $count;
    }








}

class ProtectedMembers extends ActiveRecord
{

    public static function getDb()
    {
        return Yii::createObject(Module::getInstance()->uc_db);
    }

    public static function tableName()
    {
        return '{{%protectedmembers}}';
    }
}
