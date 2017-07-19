<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\helpers;

use flipbox\meta\records\Meta as MetaRecord;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Field
{

    const TEMPLATE_PATH = 'meta' .
    DIRECTORY_SEPARATOR . '_components' .
    DIRECTORY_SEPARATOR . 'fieldtypes' .
    DIRECTORY_SEPARATOR . 'Meta';

    /**
     * @param $fieldId
     * @return string
     */
    public static function getContextById($fieldId)
    {
        return self::getContextPrefix() . $fieldId;
    }

    /**
     * @return string
     */
    public static function getContextPrefix()
    {
        return MetaRecord::tableAlias() . ':';
    }

    /**
     * @param int $id
     * @return string
     */
    public static function getContentTableName(int $id)
    {
        return '{{%' . static::getContentTableRef($id) . '}}';
    }

    /**
     * @param int $id
     * @return string
     */
    public static function getContentTableRef(int $id)
    {
        return MetaRecord::tableAlias() . 'content_' . $id;
    }
}
