<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\fields;

use Craft;
use craft\base\EagerLoadingFieldInterface;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\FieldInterface;
use craft\behaviors\FieldLayoutBehavior;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use craft\fields\Matrix;
use craft\fields\MissingField;
use craft\fields\PlainText;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\validators\ArrayValidator;
use flipbox\meta\elements\db\Meta as MetaQuery;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\helpers\Field as FieldHelper;
use flipbox\meta\Meta as MetaPlugin;
use flipbox\meta\records\Meta as MetaRecord;
use flipbox\meta\web\assets\input\Input as MetaInputAsset;
use flipbox\meta\web\assets\settings\Settings as MetaSettingsAsset;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Meta extends Field implements EagerLoadingFieldInterface
{

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('meta', 'Meta');
    }

    /**
     * @var int|null
     */
    public $max;

    /**
     * @var int|null
     */
    public $min;

    /**
     * @var string
     */
    public $selectionLabel = "Add meta";

    /**
     * @var int
     */
    public $localize = false;

    /**
     * @var int|null
     */
    public $fieldLayoutId;

    /**
     * @var string
     */
    public $template = FieldHelper::TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'layout';

    /**
     * @var bool
     */
    public $hasFieldErrors = false;

    /**
     * @inheritdoc
     */
    public static function hasContentColumn(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'fieldLayout' => [
                'class' => FieldLayoutBehavior::class,
                'elementType' => self::class
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {

        return array_merge(
            parent::rules(),
            [
                [
                    [
                        'min',
                        'max'
                    ],
                    'integer',
                    'min' => 0
                ]
            ]
        );

    }

    /**
     * @inheritdoc
     */
    public function validate($attributeNames = null, $clearErrors = true): bool
    {

        // Run basic model validation first
        $validates = parent::validate($attributeNames, $clearErrors);

        // Run field validation as well
        if (!MetaPlugin::getInstance()->getConfiguration()->validate($this)) {
            $validates = false;
        }

        return $validates;

    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml()
    {

        // Get the available field types data
        $fieldTypeInfo = $this->_getFieldOptionsForConfiguration();

        $view = Craft::$app->getView();

        $view->registerAssetBundle(MetaSettingsAsset::class);
        $view->registerJs(
            'new Craft.MetaConfiguration(' .
            Json::encode($fieldTypeInfo, JSON_UNESCAPED_UNICODE) . ', ' .
            Json::encode(Craft::$app->getView()->getNamespace(), JSON_UNESCAPED_UNICODE) .
            ');'
        );

        $view->registerTranslations('meta', [
            'New field'
        ]);

        $fieldTypeOptions = [];

        /** @var Field|string $class */
        foreach (Craft::$app->getFields()->getAllFieldTypes() as $class) {
            $fieldTypeOptions[] = [
                'value' => $class,
                'label' => $class::displayName()
            ];
        }

        // Handle missing fields
        $fields = $this->getFields();
        foreach ($fields as $i => $field) {
            if ($field instanceof MissingField) {
                $fields[$i] = $field->createFallback(PlainText::class);
                $fields[$i]->addError('type', Craft::t('app', 'The field type “{type}” could not be found.', [
                    'type' => $field->expectedType
                ]));
                $this->hasFieldErrors = true;
            }
        }
        $this->setFields($fields);

        return Craft::$app->getView()->renderTemplate(
            FieldHelper::TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'settings',
            [
                'field' => $this,
                'fieldTypes' => $fieldTypeOptions
            ]
        );

    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ElementInterface $element = null)
    {

        /** @var Element $element */

        // New element query
        $query = MetaElement::find();

        // Existing element?
        if (!empty($element->id)) {
            $query->ownerId($element->id);
        } else {
            $query->id(false);
        }

        // Set our field and site to the query
        $query
            ->fieldId($this->id)
            ->siteId($element->siteId);

        // Set the initially matched elements if $value is already set, which is the case if there was a validation
        // error or we're loading an entry revision.
        if (is_array($value) || $value === '') {
            $query->status = null;
            $query->enabledForSite = false;
            $query->limit = null;
            $query->setCachedResult($this->_createElementsFromSerializedData($value, $element));
        }

        return $query;

    }

    /**
     * @inheritdoc
     */
    public function modifyElementsQuery(ElementQueryInterface $query, $value)
    {

        /** @var ElementQuery $query */
        if ($value === 'not :empty:') {
            $value = ':notempty:';
        }

        if ($value === ':notempty:' || $value === ':empty:') {
            $alias = MetaRecord::tableAlias() . '_' . $this->handle;
            $operator = ($value === ':notempty:' ? '!=' : '=');

            $query->subQuery->andWhere(
                "(select count([[{$alias}.id]]) from " . MetaRecord::tableName() . " {{{$alias}}} where [[{$alias}.ownerId]] = [[elements.id]] and [[{$alias}.fieldId]] = :fieldId) {$operator} 0",
                [':fieldId' => $this->id]
            );
        } else if ($value !== null) {
            return false;
        }

        return null;

    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {

        $id = Craft::$app->getView()->formatInputId($this->handle);

        // Get the field data
        $fieldInfo = $this->_getFieldInfoForInput();

        Craft::$app->getView()->registerAssetBundle(MetaInputAsset::class);

        Craft::$app->getView()->registerJs('new Craft.MetaInput(' .
            '"' . Craft::$app->getView()->namespaceInputId($id) . '", ' .
            Json::encode($fieldInfo, JSON_UNESCAPED_UNICODE) . ', ' .
            '"' . Craft::$app->getView()->namespaceInputName($this->handle) . '", ' .
            ($this->min ?: 'null') . ', ' .
            ($this->max ?: 'null') .
            ');');

        Craft::$app->getView()->registerTranslations('meta', [
            'Add new',
            'Add new above'
        ]);

        if ($value instanceof MetaQuery) {
            $value
                ->limit(null)
                ->status(null)
                ->enabledForSite(false);
        }

        return Craft::$app->getView()->renderTemplate(
            FieldHelper::TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'input',
            [
                'id' => $id,
                'name' => $this->handle,
                'field' => $this,
                'elements' => $value,
                'static' => false,
                'template' => FieldHelper::defaultLayoutTemplate()
            ]
        );

    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        return [
            'validateMeta',
            [
                ArrayValidator::class,
                'min' => $this->required ? ($this->min ?: 1) : null,
                'max' => $this->max ?: null,
                'tooFew' => Craft::t('app', '{attribute} should contain at least {min, number} {min, plural, one{record} other{records}}.'),
                'tooMany' => Craft::t('app', '{attribute} should contain at most {max, number} {max, plural, one{record} other{records}}.'),
            ],
        ];
    }

    /**
     * Validates an owner element’s Meta.
     *
     * @param ElementInterface $element
     *
     * @return void
     */
    public function validateMeta(ElementInterface $element)
    {
        /** @var Element $element */
        /** @var MetaQuery $value */
        $value = $element->getFieldValue($this->handle);
        $validate = true;

        foreach ($value as $meta) {
            /** @var MetaElement $meta */
            if (!$meta->validate()) {
                $validate = false;
            }
        }

        if (!$validate) {
            $element->addError($this->handle, Craft::t('app', 'Correct the errors listed above.'));
        }
    }

    /**
     * @param mixed $value
     * @param Element|ElementInterface $element
     * @return string
     */
    public function getSearchKeywords($value, ElementInterface $element): string
    {

        /** @var MetaQuery $value */

        $keywords = [];
        $contentService = Craft::$app->getContent();

        /** @var MetaElement $meta */
        foreach ($value as $meta) {

            $originalContentTable = $contentService->contentTable;
            $originalFieldContext = $contentService->fieldContext;

            $contentService->contentTable = $meta->getContentTable();
            $contentService->fieldContext = $meta->getFieldContext();

            /** @var Field $field */
            foreach (Craft::$app->getFields()->getAllFields() as $field) {
                $fieldValue = $meta->getFieldValue($field->handle);
                $keywords[] = $field->getSearchKeywords($fieldValue, $element);
            }

            $contentService->contentTable = $originalContentTable;
            $contentService->fieldContext = $originalFieldContext;

        }

        return parent::getSearchKeywords($keywords, $element);

    }

    /**
     * todo - review this
     *
     * @inheritdoc
     */
    public function getStaticHtml($value, ElementInterface $element): string
    {

        if ($value) {
            $id = StringHelper::randomString();

            return Craft::$app->getView()->renderTemplate(
                FieldHelper::TEMPLATE_PATH . DIRECTORY_SEPARATOR . 'input',
                [
                    'id' => $id,
                    'name' => $this->handle,
                    'elements' => $value,
                    'static' => true
                ]
            );

        } else {

            Craft::$app->getView()->registerTranslations('meta', [
                'No meta'
            ]);

            return '<p class="light">' . Craft::t('meta', 'No meta') . '</p>';

        }

    }


    /**
     * @inheritdoc
     */
    public function settingsAttributes(): array
    {

        return [
            'max',
            'min',
            'selectionLabel',
            'fieldLayoutId',
            'template'
        ];

    }


    /**
     * @inheritdoc
     */
    public function getEagerLoadingMap(array $sourceElements)
    {
        // Get the source element IDs
        $sourceElementIds = [];

        foreach ($sourceElements as $sourceElement) {
            $sourceElementIds[] = $sourceElement->id;
        }

        // Return any relation data on these elements, defined with this field
        $map = (new Query())
            ->select(['ownerId as source', 'id as target'])
            ->from([MetaRecord::tableName()])
            ->where([
                'fieldId' => $this->id,
                'ownerId' => $sourceElementIds,
            ])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->all();

        return [
            'elementType' => MetaElement::class,
            'map' => $map,
            'criteria' => ['fieldId' => $this->id]
        ];
    }

    // Events
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {

        // Save field settings (and field content)
        if (!MetaPlugin::getInstance()->getConfiguration()->beforeSave($this)) {
            return false;
        }

        // Trigger an 'afterSave' event
        return parent::beforeSave($isNew);

    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {

        // Save field settings (and field content)
        MetaPlugin::getInstance()->getConfiguration()->afterSave($this);

        // Trigger an 'afterSave' event
        parent::afterSave($isNew);

    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {

        // Delete field content table
        MetaPlugin::getInstance()->getConfiguration()->beforeDelete($this);

        // Trigger a 'beforeDelete' event
        return parent::beforeDelete();

    }

    /**
     * @inheritdoc
     */
    public function afterElementSave(ElementInterface $element, bool $isNew)
    {

        // Save meta element
        MetaPlugin::getInstance()->getField()->afterElementSave($this, $element);

        // Trigger an 'afterElementSave' event
        parent::afterElementSave($element, $isNew);

    }

    /**
     * @inheritdoc
     */
    public function beforeElementDelete(ElementInterface $element): bool
    {

        // Delete meta elements
        if (!MetaPlugin::getInstance()->getField()->beforeElementDelete($this, $element)) {
            return false;
        }

        return parent::beforeElementDelete($element);

    }

    /**
     * @inheritdoc
     */
    protected function isValueEmpty($value, ElementInterface $element): bool
    {
        /** @var MetaQuery $value */
        return $value->count() === 0;
    }

    /**
     *
     * Returns info about each field type for the configurator.
     *
     * @return array
     */
    private function _getFieldOptionsForConfiguration()
    {

        $disallowedFields = [
            self::class,
            Matrix::class
        ];

        $fieldTypes = [];

        // Set a temporary namespace for these
        $originalNamespace = Craft::$app->getView()->getNamespace();
        $namespace = Craft::$app->getView()->namespaceInputName('fields[__FIELD__][settings]', $originalNamespace);
        Craft::$app->getView()->setNamespace($namespace);

        /** @var Field|string $class */
        foreach (Craft::$app->getFields()->getAllFieldTypes() as $class) {

            // Ignore disallowed fields
            if (in_array($class, $disallowedFields)) {
                continue;
            }

            Craft::$app->getView()->startJsBuffer();

            /** @var FieldInterface $field */
            $field = new $class();

            if ($settingsHtml = (string)$field->getSettingsHtml()) {
                $settingsHtml = Craft::$app->getView()->namespaceInputs($settingsHtml);
            }

            $settingsBodyHtml = $settingsHtml;
            $settingsFootHtml = Craft::$app->getView()->clearJsBuffer();

            $fieldTypes[] = [
                'type' => $class,
                'name' => $class::displayName(),
                'settingsBodyHtml' => $settingsBodyHtml,
                'settingsFootHtml' => $settingsFootHtml,
            ];

        }

        Craft::$app->getView()->setNamespace($originalNamespace);

        return $fieldTypes;

    }


    /**
     * Returns html for all associated field types for the Meta field input.
     *
     * @return array
     */
    private function _getFieldInfoForInput(): array
    {

        // Set a temporary namespace for these
        $originalNamespace = Craft::$app->getView()->getNamespace();
        $namespace = Craft::$app->getView()->namespaceInputName($this->handle . '[__META__][fields]', $originalNamespace);
        Craft::$app->getView()->setNamespace($namespace);

        $fieldLayoutFields = $this->getFields();

        // Set $_isFresh's
        foreach ($fieldLayoutFields as $field) {
            $field->setIsFresh(true);
        }

        Craft::$app->getView()->startJsBuffer();

        $bodyHtml = Craft::$app->getView()->namespaceInputs(
            Craft::$app->getView()->renderTemplate(
                '_includes/fields',
                [
                    'namespace' => null,
                    'fields' => $fieldLayoutFields
                ]
            )
        );

        // Reset $_isFresh's
        foreach ($fieldLayoutFields as $field) {
            $field->setIsFresh(null);
        }

        $footHtml = Craft::$app->getView()->clearJsBuffer();

        $fields = [
            'bodyHtml' => $bodyHtml,
            'footHtml' => $footHtml,
        ];

        // Revert namespace
        Craft::$app->getView()->setNamespace($originalNamespace);

        return $fields;

    }

    /**
     * Creates an array of elements based on the given serialized data.
     *
     * @param array|string $value The raw field value
     * @param ElementInterface|null $element The element the field is associated with, if there is one
     *
     * @return MetaElement[]
     */
    private function _createElementsFromSerializedData($value, ElementInterface $element = null): array
    {
        /** @var Element $element */

        if (!is_array($value)) {
            return [];
        }

        $oldElementsById = [];

        // Get the old elements that are still around
        if (!empty($element->id)) {
            $ownerId = $element->id;

            $ids = [];

            foreach ($value as $metaId => &$meta) {
                if (is_numeric($metaId) && $metaId !== 0) {
                    $ids[] = $metaId;
                }
            }
            unset($meta);

            if (!empty($ids)) {
                $oldMetaQuery = MetaElement::find();
                $oldMetaQuery->fieldId($this->id);
                $oldMetaQuery->ownerId($ownerId);
                $oldMetaQuery->id($ids);
                $oldMetaQuery->limit(null);
                $oldMetaQuery->status(null);
                $oldMetaQuery->enabledForSite(false);
                $oldMetaQuery->siteId($element->siteId);
                $oldMetaQuery->indexBy('id');
                $oldElementsById = $oldMetaQuery->all();
            }
        } else {
            $ownerId = null;
        }

        $elements = [];
        $sortOrder = 0;
        $prevElement = null;

        foreach ($value as $metaId => $metaData) {

            // Is this new? (Or has it been deleted?)
            if (strpos($metaId, 'new') === 0 || !isset($oldElementsById[$metaId])) {
                $meta = new MetaElement();
                $meta->fieldId = $this->id;
                $meta->ownerId = $ownerId;
                $meta->siteId = $element->siteId;
            } else {
                $meta = $oldElementsById[$metaId];
            }

            $meta->setOwner($element);
            $meta->enabled = (isset($metaData['enabled']) ? (bool)$metaData['enabled'] : true);

            // Set the content post location on the element if we can
            $fieldNamespace = $element->getFieldParamNamespace();

            if ($fieldNamespace !== null) {
                $metaFieldNamespace = ($fieldNamespace ? $fieldNamespace . '.' : '') . '.' . $this->handle . '.' . $metaId . '.fields';
                $meta->setFieldParamNamespace($metaFieldNamespace);
            }

            if (isset($metaData['fields'])) {
                $meta->setFieldValues($metaData['fields']);
            }

            $sortOrder++;
            $meta->sortOrder = $sortOrder;

            // Set the prev/next elements
            if ($prevElement) {
                /** @var ElementInterface $prevElement */
                $prevElement->setNext($meta);
                /** @var ElementInterface $meta */
                $meta->setPrev($prevElement);
            }
            $prevElement = $meta;

            $elements[] = $meta;
        }

        return $elements;

    }

    /**
     * Returns the fields associated with this element.
     *
     * @return FieldInterface[]
     */
    public function getFields(): array
    {
        return $this->getFieldLayout()->getFields();
    }

    /**
     * Sets the fields associated with this element.
     *
     * @param FieldInterface[] $fields
     *
     * @return void
     */
    public function setFields(array $fields)
    {

        $defaultFieldConfig = [
            'type' => null,
            'name' => null,
            'handle' => null,
            'instructions' => null,
            'required' => false,
            'translationMethod' => Field::TRANSLATION_METHOD_NONE,
            'translationKeyFormat' => null,
            'settings' => null,
        ];

        foreach ($fields as $fieldId => $fieldConfig) {

            if (!$fieldConfig instanceof FieldInterface) {

                /** @noinspection SlowArrayOperationsInLoopInspection */
                $fieldConfig = array_merge($defaultFieldConfig, $fieldConfig);

                $fields[$fieldId] = Craft::$app->getFields()->createField([
                    'type' => $fieldConfig['type'],
                    'id' => $fieldId,
                    'name' => $fieldConfig['name'],
                    'handle' => $fieldConfig['handle'],
                    'instructions' => $fieldConfig['instructions'],
                    'required' => (bool)$fieldConfig['required'],
                    'translationMethod' => $fieldConfig['translationMethod'],
                    'translationKeyFormat' => $fieldConfig['translationKeyFormat'],
                    'settings' => $fieldConfig['settings'],
                ]);

            }

        }

        $this->getFieldLayout()->setFields($fields);

    }

    /**
     * Returns the owner's field layout.
     *
     * @return FieldLayout
     */
    public function getFieldLayout(): FieldLayout
    {
        return $this->getFieldLayoutBehavior()->getFieldLayout();
    }

    /**
     * Sets the owner's field layout.
     *
     * @param FieldLayout $fieldLayout
     *
     * @return void
     */
    public function setFieldLayout(FieldLayout $fieldLayout)
    {
        $this->getFieldLayoutBehavior()->setFieldLayout($fieldLayout);
    }

    /**
     * @return null|\yii\base\Behavior|FieldLayoutBehavior
     */
    private function getFieldLayoutBehavior()
    {
        $this->ensureBehaviors();
        return $this->getBehavior('fieldLayout');
    }

}
