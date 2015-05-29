<?php

namespace hustshenl\ucenter\components\client\controllers;

use hustshenl\ucenter\Module;
use yii\base\Component;
use hustshenl\ucenter\components\client\models\User as UcUser;
use yii;

/**
 * Class User
 * @property \hustshenl\ucenter\Module $module
 * @package hustshenl\ucenter\components
 */
class UserController extends Component
{

    public $args = false;
    private $_module = false;

	function init() {
        parent::init();
	}

    public function getModule()
    {
        if($this->_module === false) $this->_module = Module::getInstance();
        return $this->_module;
    }

    public function login0($args)
    {
        \Yii::trace($args);
        var_dump($args);
        $ucUser = UcUser::find()->where(['username'=>$args['username']])->one();
        if(empty($ucUser->username)) {
            return -1;
        } elseif($ucUser->password != md5(md5($args['password']).$ucUser->salt)) {
            return -2;
        }
        return $ucUser['uid'];
        var_dump($ucUser);
        return 'OK';
    }
    function login($args) {
        $ip = Yii::$app->request->userIP;
        $key = [__METHOD__,$ip];

        if($args['isuid'] == 1) {
            $user = UcUser::find()->where(['uid'=>$args['username']])->one();
        } elseif($args['isuid'] == 2) {
            $user = UcUser::find()->where(['email'=>$args['username']])->one();
        } else {
            $user = UcUser::find()->where(['username'=>$args['username']])->one();
        }
        if(empty($user)) {
            $status = -1;
            $user = new UcUser();
        } elseif($user->validPassword($args['password'])) {
            $status = -2;
        } elseif($args['checkques'] && $user->validSecques($args['questionid'], $args['answer'])) {
            $status = -3;
        } else {
            $status = $user['uid'];
        }
        return [$status, $user->username, $args['password'], $user->email, 0];
    }

    function synlogin() {
        $this->init_input();
        $uid = $this->input('uid');
        if($this->app['synlogin']) {
            if($this->user = $_ENV['user']->get_user_by_uid($uid)) {
                $synstr = '';
                foreach($this->cache['apps'] as $appid => $app) {
                    if($app['synlogin'] && $app['appid'] != $this->app['appid']) {
                        $synstr .= '<script type="text/javascript" src="'.$app['url'].'/api/uc.php?time='.$this->time.'&code='.urlencode($this->authcode('action=synlogin&username='.$this->user['username'].'&uid='.$this->user['uid'].'&password='.$this->user['password']."&time=".$this->time, 'ENCODE', $app['authkey'])).'"></script>';
                    }
                }
                return $synstr;
            }
        }
        return '';
    }

    function synlogout() {
        $this->init_input();
        if($this->app['synlogin']) {
            $synstr = '';
            foreach($this->cache['apps'] as $appid => $app) {
                if($app['synlogin'] && $app['appid'] != $this->app['appid']) {
                    $synstr .= '<script type="text/javascript" src="'.$app['url'].'/api/uc.php?time='.$this->time.'&code='.urlencode($this->authcode('action=synlogout&time='.$this->time, 'ENCODE', $app['authkey'])).'"></script>';
                }
            }
            return $synstr;
        }
        return '';
    }

    function register() {
        $this->init_input();
        $username = $this->input('username');
        $password =  $this->input('password');
        $email = $this->input('email');
        $questionid = $this->input('questionid');
        $answer = $this->input('answer');
        $regip = $this->input('regip');

        if(($status = $this->_check_username($username)) < 0) {
            return $status;
        }
        if(($status = $this->_check_email($email)) < 0) {
            return $status;
        }
        $uid = $_ENV['user']->add_user($username, $password, $email, 0, $questionid, $answer, $regip);
        return $uid;
    }

    function edit() {
        $this->init_input();
        $username = $this->input('username');
        $oldpw = $this->input('oldpw');
        $newpw = $this->input('newpw');
        $email = $this->input('email');
        $ignoreoldpw = $this->input('ignoreoldpw');
        $questionid = $this->input('questionid');
        $answer = $this->input('answer');

        if(!$ignoreoldpw && $email && ($status = $this->_check_email($email, $username)) < 0) {
            return $status;
        }
        $status = $_ENV['user']->edit_user($username, $oldpw, $newpw, $email, $ignoreoldpw, $questionid, $answer);

        if($newpw && $status > 0) {
            $this->load('note');
            $_ENV['note']->add('updatepw', 'username='.urlencode($username).'&password=');
            $_ENV['note']->send();
        }
        return $status;
    }



    function logincheck() {
        $this->init_input();
        $username = $this->input('username');
        $ip = $this->input('ip');
        return $_ENV['user']->can_do_login($username, $ip);
    }

    function check_email() {
        $this->init_input();
        $email = $this->input('email');
        return $this->_check_email($email);
    }

    function check_username() {
        $this->init_input();
        $username = $this->input('username');
        if(($status = $this->_check_username($username)) < 0) {
            return $status;
        } else {
            return 1;
        }
    }

    function get_user() {
        $this->init_input();
        $username = $this->input('username');
        if(!$this->input('isuid')) {
            $status = $_ENV['user']->get_user_by_username($username);
        } else {
            $status = $_ENV['user']->get_user_by_uid($username);
        }
        if($status) {
            return array($status['uid'],$status['username'],$status['email']);
        } else {
            return 0;
        }
    }


    function getprotected() {
        $this->init_input();
        $protectedmembers = $this->db->fetch_all("SELECT uid,username FROM ".UC_DBTABLEPRE."protectedmembers GROUP BY username");
        return $protectedmembers;
    }

    function delete() {
        $this->init_input();
        $uid = $this->input('uid');
        return $_ENV['user']->delete_user($uid);
    }

    function addprotected() {
        $this->init_input();
        $username = $this->input('username');
        $admin = $this->input('admin');
        $appid = $this->app['appid'];
        $usernames = (array)$username;
        foreach($usernames as $username) {
            $user = $_ENV['user']->get_user_by_username($username);
            $uid = $user['uid'];
            $this->db->query("REPLACE INTO ".UC_DBTABLEPRE."protectedmembers SET uid='$uid', username='$username', appid='$appid', dateline='{$this->time}', admin='$admin'", 'SILENT');
        }
        return $this->db->errno() ? -1 : 1;
    }

    function deleteprotected() {
        $this->init_input();
        $username = $this->input('username');
        $appid = $this->app['appid'];
        $usernames = (array)$username;
        foreach($usernames as $username) {
            $this->db->query("DELETE FROM ".UC_DBTABLEPRE."protectedmembers WHERE username='$username' AND appid='$appid'");
        }
        return $this->db->errno() ? -1 : 1;
    }

    function merge() {
        $this->init_input();
        $oldusername = $this->input('oldusername');
        $newusername = $this->input('newusername');
        $uid = $this->input('uid');
        $password = $this->input('password');
        $email = $this->input('email');
        if(($status = $this->_check_username($newusername)) < 0) {
            return $status;
        }
        $uid = $_ENV['user']->add_user($newusername, $password, $email, $uid);
        $this->db->query("DELETE FROM ".UC_DBTABLEPRE."mergemembers WHERE appid='".$this->app['appid']."' AND username='$oldusername'");
        return $uid;
    }

    function merge_remove() {
        $this->init_input();
        $username = $this->input('username');
        $this->db->query("DELETE FROM ".UC_DBTABLEPRE."mergemembers WHERE appid='".$this->app['appid']."' AND username='$username'");
        return NULL;
    }

    function _check_username($username) {
        $username = addslashes(trim(stripslashes($username)));
        if(!$_ENV['user']->check_username($username)) {
            return UC_USER_CHECK_USERNAME_FAILED;
        } elseif(!$_ENV['user']->check_usernamecensor($username)) {
            return UC_USER_USERNAME_BADWORD;
        } elseif($_ENV['user']->check_usernameexists($username)) {
            return UC_USER_USERNAME_EXISTS;
        }
        return 1;
    }

    function _check_email($email, $username = '') {
        if(empty($this->settings)) {
            $this->settings = $this->cache('settings');
        }
        if(!$_ENV['user']->check_emailformat($email)) {
            return UC_USER_EMAIL_FORMAT_ILLEGAL;
        } elseif(!$_ENV['user']->check_emailaccess($email)) {
            return UC_USER_EMAIL_ACCESS_ILLEGAL;
        } elseif(!$this->settings['doublee'] && $_ENV['user']->check_emailexists($email, $username)) {
            return UC_USER_EMAIL_EXISTS;
        } else {
            return 1;
        }
    }

    function uploadavatar() {
    }

    function rectavatar() {
    }
    function flashdata_decode($s) {
    }



















	function init_var() {
		$this->time = time();
		$cip = getenv('HTTP_CLIENT_IP');
		$xip = getenv('HTTP_X_FORWARDED_FOR');
		$rip = getenv('REMOTE_ADDR');
		$srip = $_SERVER['REMOTE_ADDR'];
		if($cip && strcasecmp($cip, 'unknown')) {
			$this->onlineip = $cip;
		} elseif($xip && strcasecmp($xip, 'unknown')) {
			$this->onlineip = $xip;
		} elseif($rip && strcasecmp($rip, 'unknown')) {
			$this->onlineip = $rip;
		} elseif($srip && strcasecmp($srip, 'unknown')) {
			$this->onlineip = $srip;
		}
		preg_match("/[\d\.]{7,15}/", $this->onlineip, $match);
		$this->onlineip = $match[0] ? $match[0] : 'unknown';
		$this->app['appid'] = UC_APPID;
	}

	function init_input() {

	}

	function init_db() {
		if(function_exists("mysql_connect")) {
			require_once UC_ROOT.'lib/db.class.php';
		} else {
			require_once UC_ROOT.'lib/dbi.class.php';
		}
		$this->db = new ucclient_db();
		$this->db->connect(UC_DBHOST, UC_DBUSER, UC_DBPW, '', UC_DBCHARSET, UC_DBCONNECT, UC_DBTABLEPRE);
	}

	function load($model, $base = NULL) {
		$base = $base ? $base : $this;
		if(empty($_ENV[$model])) {
			require_once UC_ROOT."./model/$model.php";
			eval('$_ENV[$model] = new '.$model.'model($base);');
		}
		return $_ENV[$model];
	}

	function date($time, $type = 3) {
		if(!$this->settings) {
			$this->settings = $this->cache('settings');
		}
		$format[] = $type & 2 ? (!empty($this->settings['dateformat']) ? $this->settings['dateformat'] : 'Y-n-j') : '';
		$format[] = $type & 1 ? (!empty($this->settings['timeformat']) ? $this->settings['timeformat'] : 'H:i') : '';
		return gmdate(implode(' ', $format), $time + $this->settings['timeoffset']);
	}

	function page_get_start($page, $ppp, $totalnum) {
		$totalpage = ceil($totalnum / $ppp);
		$page =  max(1, min($totalpage,intval($page)));
		return ($page - 1) * $ppp;
	}

	function implode($arr) {
		return "'".implode("','", (array)$arr)."'";
	}

	function &cache($cachefile) {
		static $_CACHE = array();
		if(!isset($_CACHE[$cachefile])) {
			$cachepath = UC_DATADIR.'./cache/'.$cachefile.'.php';
			if(!file_exists($cachepath)) {
				$this->load('cache');
				$_ENV['cache']->updatedata($cachefile);
			} else {
				include_once $cachepath;
			}
		}
		return $_CACHE[$cachefile];
	}

	function get_setting($k = array(), $decode = FALSE) {
		$return = array();
		$sqladd = $k ? "WHERE k IN (".$this->implode($k).")" : '';
		$settings = $this->db->fetch_all("SELECT * FROM ".UC_DBTABLEPRE."settings $sqladd");
		if(is_array($settings)) {
			foreach($settings as $arr) {
				$return[$arr['k']] = $decode ? unserialize($arr['v']) : $arr['v'];
			}
		}
		return $return;
	}

	function init_cache() {
		$this->settings = $this->cache('settings');
		$this->cache['apps'] = $this->cache('apps');

		if(PHP_VERSION > '5.1') {
			$timeoffset = intval($this->settings['timeoffset'] / 3600);
			@date_default_timezone_set('Etc/GMT'.($timeoffset > 0 ? '-' : '+').(abs($timeoffset)));
		}
	}

	function cutstr($string, $length, $dot = ' ...') {
		if(strlen($string) <= $length) {
			return $string;
		}

		$string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);

		$strcut = '';
		if(strtolower(UC_CHARSET) == 'utf-8') {

			$n = $tn = $noc = 0;
			while($n < strlen($string)) {

				$t = ord($string[$n]);
				if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
					$tn = 1; $n++; $noc++;
				} elseif(194 <= $t && $t <= 223) {
					$tn = 2; $n += 2; $noc += 2;
				} elseif(224 <= $t && $t < 239) {
					$tn = 3; $n += 3; $noc += 2;
				} elseif(240 <= $t && $t <= 247) {
					$tn = 4; $n += 4; $noc += 2;
				} elseif(248 <= $t && $t <= 251) {
					$tn = 5; $n += 5; $noc += 2;
				} elseif($t == 252 || $t == 253) {
					$tn = 6; $n += 6; $noc += 2;
				} else {
					$n++;
				}

				if($noc >= $length) {
					break;
				}

			}
			if($noc > $length) {
				$n -= $tn;
			}

			$strcut = substr($string, 0, $n);

		} else {
			for($i = 0; $i < $length; $i++) {
				$strcut .= ord($string[$i]) > 127 ? $string[$i].$string[++$i] : $string[$i];
			}
		}

		$strcut = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $strcut);

		return $strcut.$dot;
	}

	function init_note() {
		if($this->note_exists()) {
			$this->load('note');
			$_ENV['note']->send();
		}
	}

	function note_exists() {
		$noteexists = $this->db->result_first("SELECT value FROM ".UC_DBTABLEPRE."vars WHERE name='noteexists".UC_APPID."'");
		if(empty($noteexists)) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	function init_mail() {
		if($this->mail_exists() && !getgpc('inajax')) {
			$this->load('mail');
			$_ENV['mail']->send();
		}
	}

	function authcode($string, $operation = 'DECODE', $key = '', $expiry = 0) {
		return uc_authcode($string, $operation, $key, $expiry);
	}
	function unserialize($s) {
		return uc_unserialize($s);
	}

	function input($k) {
		return isset($this->input[$k]) ? (is_array($this->input[$k]) ? $this->input[$k] : trim($this->input[$k])) : NULL;
	}

	function mail_exists() {
		$mailexists = $this->db->result_first("SELECT value FROM ".UC_DBTABLEPRE."vars WHERE name='mailexists'");
		if(empty($mailexists)) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	function dstripslashes($string) {
		if(is_array($string)) {
			foreach($string as $key => $val) {
				$string[$key] = $this->dstripslashes($val);
			}
		} else {
			$string = stripslashes($string);
		}
		return $string;
	}

}

?>