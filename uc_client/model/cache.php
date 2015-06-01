<?php

/*
	[UCenter] (C)2001-2099 Comsenz Inc.
	This is NOT a freeware, use is subject to license terms

	$Id: cache.php 1059 2011-03-01 07:25:09Z monkey $
*/

!defined('IN_UC') && exit('Access Denied');

if(!function_exists('file_put_contents')) {
	function file_put_contents($filename, $s) {
		$fp = @fopen($filename, 'w');
		@fwrite($fp, $s);
		@fclose($fp);
	}
}

class cachemodel {

	var $db;
	var $base;
	var $map;

	function __construct(&$base) {
		$this->cachemodel($base);
	}

	function cachemodel(&$base) {
		$this->base = $base;
		$this->db = $base->db;
		$this->map = array(
			'settings' => array('settings'),
			'badwords' => array('badwords'),
			'apps' => array('apps')
		);
	}

	function updatedata($cachefile = '') {
		if($cachefile) {
			foreach((array)$this->map[$cachefile] as $modules) {
                $key = 'uc_client'.$cachefile;
                $v = [];
				foreach((array)$modules as $m) {
					$method = "_get_$m";
                    $v[$m] = $this->$method();
				}
                \Yii::$app->cache->set($key,$v);
			}
		} else {
			foreach((array)$this->map as $file => $modules) {
                $key = 'uc_client'.$file;
                $v = [];
				foreach($modules as $m) {
					$method = "_get_$m";
                    $v[$m] = $this->$method();
				}
                \Yii::$app->cache->set($key,$v);
			}
		}
	}

	function updatetpl() {

	}

	function _get_badwords() {
		$data = $this->db->fetch_all("SELECT * FROM ".UC_DBTABLEPRE."badwords");
		$return = array();
		if(is_array($data)) {
			foreach($data as $k => $v) {
				$return['findpattern'][$k] = $v['findpattern'];
				$return['replace'][$k] = $v['replacement'];
			}
		}
		return $return;
	}

	function _get_apps() {
		$this->base->load('app');
		$apps = $_ENV['app']->get_apps();
		$apps2 = array();
		if(is_array($apps)) {
			foreach($apps as $v) {
				$v['extra'] = unserialize($v['extra']);
				$apps2[$v['appid']] = $v;
			}
		}
		return $apps2;
	}

	function _get_settings() {
		return $this->base->get_setting();
	}

}

?>