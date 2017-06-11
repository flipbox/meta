<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\services;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\records\Field;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\fields\Meta as MetaField;
use flipbox\meta\helpers\Field as FieldHelper;
use flipbox\meta\Meta as MetaPlugin;
use flipbox\meta\migrations\ContentTable;
use flipbox\meta\records\Meta as MetaRecord;
use yii\base\Component;
use yii\base\Exception;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Configuration extends Component
{

    /**
     * @param MetaField $metaField
     * @return bool
     */
    public function beforeSave(MetaField $metaField)
    {
        if (!$metaField->getIsNew()) {
            /** @var Field $fieldRecord */
            if ($oldFieldRecord = Field::findOne($metaField->id)) {
                /** @var MetaField $oldField */
                $oldField = Craft::$app->getFields()->createField(
                    $oldFieldRecord->toArray([
                        'id',
                        'type',
                        'name',
                        'handle',
                        'settings'
                    ])
                );

                // Delete the old field layout
                if ($oldField->fieldLayoutId) {
                    return Craft::$app->getFields()->deleteLayoutById($oldField->fieldLayoutId);
                }
            }
        }

        return true;
    }

    /**
     * Saves an Meta field's settings.
     *
     * @param MetaField $metaField
     *
     * @throws \Exception
     * @return boolean Whether the settings saved successfully.
     */
    public function afterSave(MetaField $metaField)
    {

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {

            /** @var \craft\services\Content $contentService */
            $contentService = Craft::$app->getContent();

            /** @var \craft\services\Fields $fieldsService */
            $fieldsService = Craft::$app->getFields();

            /** @var \flipbox\meta\services\Field $metaFieldService */
            $metaFieldService = MetaPlugin::getInstance()->getField();

            // Create the content table first since the element fields will need it
            $oldContentTable = $metaFieldService->getContentTableName($metaField, true);
            $newContentTable = $metaFieldService->getContentTableName($metaField);
            if ($newContentTable === false) {
                throw new Exception('There was a problem getting the new content table name.');
            }

            // Get the originals
            $originalContentTable = $contentService->contentTable;
            $originalFieldContext = $contentService->fieldContext;

            // Set our content table
            $contentService->contentTable = $oldContentTable;

            // Set our field context
            $contentService->fieldContext = FieldHelper::getContextById($metaField->id);

            // Get existing fields
            $oldFieldsById = ArrayHelper::index($fieldsService->getAllFields(), 'id');

            /** @var \craft\base\Field $field */
            foreach ($metaField->getFields() as $field) {
                if (!$field->getIsNew()) {
                    ArrayHelper::remove($oldFieldsById, $field->id);
                }
            }

            // Drop the old fields
            foreach ($oldFieldsById as $field) {
                if (!$fieldsService->deleteField($field)) {
                    throw new Exception(Craft::t('app', 'An error occurred while deleting this Meta field.'));
                }
            }

            // Refresh the schema cache
            Craft::$app->getDb()->getSchema()->refresh();

            // Do we need to create/rename the content table?
            if (!Craft::$app->getDb()->tableExists($newContentTable)) {
                if ($oldContentTable && Craft::$app->getDb()->tableExists($oldContentTable)) {
                    MigrationHelper::renameTable($oldContentTable, $newContentTable);
                } else {
                    $this->createContentTable($newContentTable);
                }
            }

            // Save the fields and field layout
            // -------------------------------------------------------------

            $fieldLayoutFields = [];
            $sortOrder = 0;

            // Set our content table
            $contentService->contentTable = $newContentTable;
            // Save field
            /** @var \craft\base\Field $field */
            foreach ($metaField->getFields() as $field) {

                // Save field (we validated earlier)
                if (!$fieldsService->saveField($field, false)) {
                    throw new Exception('An error occurred while saving this Meta field.');
                }

                // Set sort order
                $field->sortOrder = ++$sortOrder;

                $fieldLayoutFields[] = $field;

            }

            // Revert to originals
            $contentService->contentTable = $originalContentTable;
            $contentService->fieldContext = $originalFieldContext;

            $fieldLayoutTab = new FieldLayoutTab();
            $fieldLayoutTab->name = 'Fields';
            $fieldLayoutTab->sortOrder = 1;
            $fieldLayoutTab->setFields($fieldLayoutFields);

            $fieldLayout = new FieldLayout();
            $fieldLayout->type = MetaElement::class;
            $fieldLayout->setTabs([$fieldLayoutTab]);
            $fieldLayout->setFields($fieldLayoutFields);

            $fieldsService->saveLayout($fieldLayout);

            // Update the element & record with our new field layout ID
            $metaField->setFieldLayout($fieldLayout);
            $metaField->fieldLayoutId = (int)$fieldLayout->id;

            // Save the fieldLayoutId via settings
            /** @var Field $fieldRecord */
            $fieldRecord = Field::findOne($metaField->id);
            $fieldRecord->settings = $metaField->getSettings();

            if ($fieldRecord->save(true, ['settings'])) {

                // Commit field changes
                $transaction->commit();

                return true;

            } else {

                $metaField->addError('settings', Craft::t('meta', 'Unable to save settings.'));

            }

        } catch (\Exception $e) {

            $transaction->rollback();

            throw $e;
        }

        $transaction->rollback();

        return false;

    }

    /**
     * Deletes an Meta field and content table.
     *
     * @param MetaField $field The Meta field.
     *
     * @throws \Exception
     * @return boolean Whether the field was deleted successfully.
     */
    public function beforeDelete(MetaField $field)
    {

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // Delete field layout
            Craft::$app->getFields()->deleteLayoutById($field->fieldLayoutId);

            // Get content table name
            $contentTableName = MetaPlugin::getInstance()->getField()->getContentTableName($field);

            // Drop the content table
            Craft::$app->getDb()->createCommand()->dropTableIfExists($contentTableName)->execute();

            // find any of the context fields
            $subFieldRecords = Field::find()
                ->andWhere(['like', 'context', MetaRecord::tableAlias() . ':%', false])
                ->all();

            // Delete them
            /** @var MetaRecord $subFieldRecord */
            foreach ($subFieldRecords as $subFieldRecord) {
                Craft::$app->getFields()->deleteFieldById($subFieldRecord->id);
            }

            // All good
            $transaction->commit();

            return true;
        } catch (\Exception $e) {
            // Revert
            $transaction->rollback();

            throw $e;
        }
    }


    /**
     * Validates a Meta field's settings.
     *
     * If the settings don’t validate, any validation errors will be stored on the settings model.
     *
     * @param MetaField $metaField The Meta field
     *
     * @return boolean Whether the settings validated.
     */
    public function validate(MetaField $metaField): bool
    {
        $validates = true;

        // Can't validate multiple new rows at once so we'll need to give these temporary context to avoid false unique
        // handle validation errors, and just validate those manually. Also apply the future fieldColumnPrefix so that
        // field handle validation takes its length into account.
        $contentService = Craft::$app->getContent();
        $originalFieldContext = $contentService->fieldContext;

        $contentService->fieldContext = StringHelper::randomString(10);

        /** @var Field $field */
        foreach ($metaField->getFields() as $field) {
            $field->validate();
            if ($field->hasErrors()) {
                $metaField->hasFieldErrors = true;
                $validates = false;
            }
        }

        $contentService->fieldContext = $originalFieldContext;

        return $validates;

    }

    /**
     * @inheritdoc
     */
    private function createContentTable($tableName)
    {
        $migration = new ContentTable([
            'tableName' => $tableName
        ]);

        ob_start();
        $migration->up();
        ob_end_clean();

    }
}
