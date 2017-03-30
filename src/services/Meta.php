<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\services;

use Craft;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\spark\services\Element;
use yii\base\ErrorException as Exception;

/**
 * @package flipbox\meta\services
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Meta extends Element
{

    /**
     * @inheritdoc
     */
    public static function elementClass(): string
    {
        return MetaElement::class;
    }

    /*******************************************
     * EXCEPTIONS
     *******************************************/

    /**
     * @throws Exception
     */
    protected function notFoundException()
    {

        throw new Exception(Craft::t(
            'meta',
            'Meta does not exist.'
        ));

    }

    /**
     * @param int|null $id
     * @throws Exception
     */
    protected function notFoundByIdException(int $id = null)
    {

        throw new Exception(Craft::t(
            'meta',
            'Meta does not exist with the id "{id}".',
            ['id' => $id]
        ));

    }

}
