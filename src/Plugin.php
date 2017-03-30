<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\events\RegisterComponentTypesEvent;
use craft\records\Field as FieldRecord;
use craft\services\Elements;
use craft\services\Fields;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\fields\Meta as MetaFieldType;
use flipbox\meta\web\twig\variables\Meta as MetaVariable;
use yii\base\Event;

/**
 * @package flipbox\meta
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Plugin extends BasePlugin
{

    /**
     * @inheritdoc
     */
    public function init()
    {

        // Do parent
        parent::init();

        // Register our elements
        Event::on(
            Elements::class,
            Elements::EVENT_REGISTER_ELEMENT_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = MetaElement::class;
            }
        );

        // Register our field types
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = MetaFieldType::class;
            }
        );

    }

    /**
     * @inheritdoc
     */
    public function defineTemplateComponent()
    {
        return MetaVariable::class;
    }

    /**
     * @inheritdoc
     */
    public function beforeUninstall(): bool
    {

        // Get field all fields associated to this plugin
        $existingFieldRecords = FieldRecord::findAll([
            'type' => MetaFieldType::class
        ]);

        // Delete them
        foreach ($existingFieldRecords as $existingFieldRecord) {
            Craft::$app->getFields()->deleteFieldById($existingFieldRecord->id);
        }

        return true;

    }

    /*******************************************
     * SERVICES
     *******************************************/

    /**
     * @return object|services\Meta
     */
    public function getMeta()
    {
        return $this->get('meta');
    }

    /**
     * @return object|services\Field
     */
    public function getField()
    {
        return $this->get('field');
    }

    /**
     * @return object|services\Configuration
     */
    public function getConfiguration()
    {
        return $this->get('configuration');
    }

}
