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

    private $_module = false;

    public function init()
    {
        parent::init();
        include_once(Yii::getAlias('@hustshenl/ucenter/uc_client/').'config.inc.php');
        include_once(Yii::getAlias('@hustshenl/ucenter/uc_client/').'client.php');
    }
    public function getModule()
    {
        if($this->_module === false) $this->_module = Module::getInstance();
        return $this->_module;
    }

    public function __call($name, $params)
    {
        $ucFunctionName = 'uc_'.$name;
        if(function_exists($ucFunctionName)){
            return call_user_func_array($ucFunctionName, $params);
        }
        return parent::__call($name, $params);
    }
}
