<?php
/**
 * @Author shen@shenl.com
 * @Create Time: 2015/5/25 14:17
 * @Description:
 */

namespace hustshenl\ucenter\models;

use Yii;

/**
 * This is the model class for table "{{%auth}}".
 *
 * @property integer $id
 * @property integer $user_id
 * @property string $source
 * @property string $source_id
 * @property integer $status
 * @property integer $created_at
 * @property integer $updated_at
 */
class Auth extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%auth}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'source', 'source_id'], 'required'],
            [['user_id', 'status', 'created_at', 'updated_at'], 'integer'],
            [['source', 'source_id'], 'string', 'max' => 255],
            [['status'], 'default', 'value' => 1]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('common', 'ID'),
            'user_id' => Yii::t('common', '用户ID'),
            'source' => Yii::t('common', '来源ID'),
            'source_id' => Yii::t('common', '来源名'),
            'status' => Yii::t('common', '状态'),
            'created_at' => Yii::t('common', 'Created At'),
            'updated_at' => Yii::t('common', 'Updated At'),
        ];
    }
}