<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\web\assets\settings;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
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
