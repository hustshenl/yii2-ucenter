<?php

/**
 * @var $module \hustshenl\ucenter\Module;
 */
$module = \hustshenl\ucenter\Module::getInstance();
define('UC_CONNECT', $module->uc_connect);				// 连接 UCenter 的方式: mysql/NULL, 默认为空时为 fscoketopen()
							// mysql 是直接连接的数据库, 为了效率, 建议采用 mysql
//数据库相关 (mysql 连接时, 并且没有设置 UC_DBLINK 时, 需要配置以下变量)

define('UC_DBHOST', $module->uc_db['host']);			// UCenter 数据库主机
define('UC_DBUSER', $module->uc_db['username']);				// UCenter 数据库用户名
define('UC_DBPW', $module->uc_db['password']);					// UCenter 数据库密码
define('UC_DBNAME', $module->uc_db['dbname']);				// UCenter 数据库名称
define('UC_DBCHARSET', $module->uc_db['charset']);				// UCenter 数据库字符集
define('UC_DBTABLEPRE', $module->uc_db['tablePrefix']);			// UCenter 数据库表前缀
define('UC_DBCONNECT', 0);

//通信相关
define('UC_KEY', $module->uc_key);				// 与 UCenter 的通信密钥, 要与 UCenter 保持一致
define('UC_API', $module->uc_api);	// UCenter 的 URL 地址, 在调用头像时依赖此常量
define('UC_CHARSET', $module->uc_charset);				// UCenter 的字符集
define('UC_IP', $module->uc_ip);					// UCenter 的 IP, 当 UC_CONNECT 为非 mysql 方式时, 并且当前应用服务器解析域名有问题时, 请设置此值
define('UC_APPID', $module->uc_appid);					// 当前应用的 ID