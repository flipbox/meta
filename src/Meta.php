<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\records\Field as FieldRecord;
use craft\services\Elements;
use craft\services\Fields;
use craft\web\twig\variables\CraftVariable;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\fields\Meta as MetaFieldType;
use flipbox\meta\web\twig\variables\Meta as MetaVariable;
use yii\base\Event;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Meta extends Plugin
{

    /**
     * @inheritdoc
     */
    public function init()
    {
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

        // Twig variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('meta', MetaVariable::class);
            }
        );
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
     * @return services\Meta
     */
    public function getMeta()
    {
        return $this->get('meta');
    }

    /**
     * @return services\Field
     */
    public function getField()
    {
        return $this->get('field');
    }

    /**
     * @return services\Configuration
     */
    public function getConfiguration()
    {
        return $this->get('configuration');
    }
}
