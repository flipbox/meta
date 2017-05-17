<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\web\twig\variables;

use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\Meta as MetaPlugin;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Meta
{

    /**
     * @param null $criteria
     * @return MetaElement
     */
    public function create($criteria = null)
    {

        /** @var MetaElement $element */
        $element = MetaPlugin::getInstance()->getMeta()->create($criteria);

        return $element;

    }

}
