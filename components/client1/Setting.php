<?php
/**
 * @Author shen@shenl.com
 * @Create Time: 2015/5/30 15:44
 * @Description:
 */

namespace hustshenl\ucenter\components\client;


use hustshenl\ucenter\Module;
use yii\base\Component;
use yii;
use hustshenl\ucenter\components\client\models\Setting as SettingModel;

class Setting extends Component
{
    private $caheDuration;
    public function init()
    {
        parent::init();
        $this->caheDuration = Module::getInstance()->caheDuration;
    }
    public function get($k,$decode = false)
    {
        $key = [__METHOD__,$k];
        $setting = Yii::$app->cache->get($key);
        if($setting !== false) return $setting;
        $model = SettingModel::findOne($k);
        $setting = $decode ? unserialize($model->v) : $model->v;
        Yii::$app->cache->set($key,$setting, $this->caheDuration);
        return $setting;
    }
}