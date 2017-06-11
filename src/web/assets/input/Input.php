<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace flipbox\meta\web\assets\input;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Asset bundle for Matrix fields
 */
class Input extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {

        $this->js = [
            'MetaInput' . $this->dotJs(),
        ];

        $this->css = [
            'MetaInput.css'
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
