<?php
/**
 * Meta Plugin for Craft CMS
 *
 * @package   Meta
 * @author    Flipbox Factory
 * @copyright Copyright (c) 2015, Flipbox Digital
 * @link      https://flipboxfactory.com/craft/meta/
 * @license   https://flipboxfactory.com/craft/meta/license
 */

namespace flipbox\meta\web\assets\settings;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Application asset bundle.
 */
class Settings extends AssetBundle
{

    /**
     * @inheritdoc
     */
    public function init()
    {

        $this->js = [
            'MetaConfiguration' . $this->dotJs()
        ];

        $this->css = [
            'MetaConfiguration.css'
        ];

        parent::init();

    }

    /**
     * @inheritdoc
     */
    public $sourcePath = __DIR__ . '/dist';

    /**
     * @inheritdoc
     */
    public $depends = [
        CpAsset::class,
    ];

}
