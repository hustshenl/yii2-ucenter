<?php
/**
 * @Author shen@shenl.com
 * @Create Time: 2015/4/14 11:45
 * @Description:
 */

namespace hustshenl\ucenter\behaviors;

use yii\db\BaseActiveRecord;
use yii\behaviors\AttributeBehavior;
use yii\db\Expression;
use yii;

class IPBehavior extends AttributeBehavior
{
    public $updatedAtAttribute = 'login_ip';
    public $createdAtAttribute = 'register_ip';
    public $value;


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        if (empty($this->attributes)) {
            $this->attributes = [
                BaseActiveRecord::EVENT_BEFORE_INSERT => [$this->createdAtAttribute, $this->updatedAtAttribute],
                BaseActiveRecord::EVENT_BEFORE_UPDATE => $this->updatedAtAttribute,
            ];
        }
    }

    /**
     * @inheritdoc
     */
    protected function getValue($event)
    {
        if ($this->value instanceof Expression) {
            return $this->value;
        } else {
            return $this->value !== null ? call_user_func($this->value, $event) : ip2long(Yii::$app->request->getUserIP());
        }
    }

    /**
     * Updates a timestamp attribute to the current timestamp.
     *
     * ```php
     * $model->touch('lastVisit');
     * ```
     * @param string $attribute the name of the attribute to update.
     */
    public function touch($attribute)
    {
        $this->owner->updateAttributes(array_fill_keys((array) $attribute, $this->getValue(null)));
    }
}