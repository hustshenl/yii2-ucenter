<?php

namespace hustshenl\ucenter;

use Yii;
use yii\helpers\Inflector;


class Module extends \yii\base\Module
{
    /**
     * @inheritdoc
     */
    public $defaultRoute = 'assignment';

    /**
     * @var array 
     * @see [[items]]
     */
    private $_menus = [];

    /**
     * @var array 
     * @see [[items]]
     */
    private $_coreItems = [
        'assignment' => 'Assignments',
        'role' => 'Roles',
        'permission' => 'Permissions',
        'route' => 'Routes',
        'rule' => 'Rules',
        'menu' => 'Menus',
    ];

    /**
     * @var array 
     * @see [[items]]
     */
    private $_normalizeMenus;

    /**
     * Nav bar items
     * @var array  
     */
    public $navbar;

    /**
     * @var string Main layout using for module. Default to layout of parent module.
     * Its used when `layout` set to 'left-menu', 'right-menu' or 'top-menu'.
     */
    public $mainLayout = '@hustshenl/ucenter/views/layouts/main.php';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if (!isset(Yii::$app->i18n->translations['hustshenl-ucenter'])) {
            Yii::$app->i18n->translations['hustshenl-ucenter'] = [
                'class' => 'yii\i18n\PhpMessageSource',
                'basePath' => '@hustshenl/ucenter/messages',
                'sourceLanguage' => 'en-US',
                'fileMap' => [
                    'hustshenl-ucenter' => 'app.php',
                ],
            ];
        }
        //user did not define the Navbar?
        /*if ($this->navbar === null) {
            $this->navbar = [
                ['label' => Yii::t('hustshenl-ucenter', 'Help'), 'url' => 'https://github.com/hustshenl/ucenter/blob/master/docs/guide/basic-usage.md'],
                ['label' => Yii::t('hustshenl-ucenter', 'Application'), 'url' => Yii::$app->homeUrl]
            ];
        }*/
    }


}