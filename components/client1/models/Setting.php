<?php

namespace hustshenl\ucenter\components\client\models;

use hustshenl\ucenter\components\UcReceiver;
use hustshenl\ucenter\Module;
use yii\base\Component;
use yii\db\ActiveRecord;
use yii;


/**
 * Class Setting
 * @property \hustshenl\ucenter\Module $module
 * @package hustshenl\ucenter\components
 */
class Setting extends ActiveRecord
{

    public $args = false;
    private $_module = false;

	function init() {
        parent::init();
	}
    public static function getDb()
    {
        return Yii::createObject(Module::getInstance()->uc_db);
    }
    public static function tableName()
    {
        return '{{%settings}}';
    }
    public function getModule()
    {
        if($this->_module === false) $this->_module = Module::getInstance();
        return $this->_module;
    }

}
