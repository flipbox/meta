<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\elements;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\db\ElementQueryInterface;
use craft\fields\MissingField;
use craft\helpers\ArrayHelper;
use craft\validators\SiteIdValidator;
use flipbox\meta\elements\db\Meta as MetaQuery;
use flipbox\meta\fields\Meta as MetaField;
use flipbox\meta\helpers\Field as FieldHelper;
use flipbox\meta\Plugin as MetaPlugin;
use flipbox\meta\records\Meta as MetaRecord;
use flipbox\spark\helpers\ElementHelper;
use yii\base\Exception;

/**
 * @package flipbox\meta\elements
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Meta extends Element
{

    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('meta', 'Meta');
    }

    /**
     * @inheritdoc
     */
    public static function refHandle()
    {
        return 'meta';
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     *
     * @return MetaQuery
     */
    public static function find(): ElementQueryInterface
    {
        return new MetaQuery(get_called_class());
    }

    /**
     * @inheritdoc
     */
    public static function eagerLoadingMap(array $sourceElements, string $handle)
    {

        $fieldId = $sourceElements[0]->fieldId;

        // Create field context (meta:{id})
        $fieldContext = FieldHelper::getContextById($fieldId);

        // Get all fields (by context)
        $fields = ArrayHelper::index(
            Craft::$app->getFields()->getAllFields($fieldContext),
            'handle'
        );

        // Does field exist?
        if (ArrayHelper::keyExists($handle, $fields)) {

            $contentService = Craft::$app->getContent();

            $originalFieldContext = $contentService->fieldContext;
            $contentService->fieldContext = $fieldContext;

            $map = parent::eagerLoadingMap($sourceElements, $handle);

            $contentService->fieldContext = $originalFieldContext;

            return $map;

        }

        return parent::eagerLoadingMap($sourceElements, $handle);

    }

    // Properties
    // =========================================================================

    /**
     * @var int|null Field ID
     */
    public $fieldId;

    /**
     * @var int|null Owner ID
     */
    public $ownerId;

    /**
     * @var int|null Owner site ID
     */
    public $ownerSiteId;

    /**
     * @var int|null Sort order
     */
    public $sortOrder;

    /**
     * @var ElementInterface|false|null The owner element, or false if [[ownerId]] is invalid
     */
    private $_owner;


    // Public Methods
    // =========================================================================

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
                        'fieldId',
                        'ownerId',
                        'fieldId'
                    ],
                    'number',
                    'integerOnly' => true
                ],
                [
                    [
                        'ownerSiteId'
                    ],
                    SiteIdValidator::class
                ],
                [
                    [
                        'ownerId',
                        'ownerSiteId',
                        'fieldId',
                        'sortOrder'
                    ],
                    'safe',
                    'on' => [
                        ElementHelper::SCENARIO_POPULATE,
                        ElementHelper::SCENARIO_DEFAULT
                    ]
                ]
            ]
        );

    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return $this->getField()->getFieldLayout();
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        // If the Matrix field is translatable, than each individual block is tied to a single site, and thus aren't
        // translatable. Otherwise all elements belong to all sites, and their content is translatable.

        if ($this->ownerSiteId !== null) {
            return [$this->ownerSiteId];
        }

        $owner = $this->getOwner();

        if ($owner) {
            // Just send back an array of site IDs -- don't pass along enabledByDefault configs
            $siteIds = [];

            foreach (ElementHelper::supportedSitesForElement($owner) as $siteInfo) {
                $siteIds[] = $siteInfo['siteId'];
            }

            return $siteIds;
        }

        return [Craft::$app->getSites()->getPrimarySite()->id];
    }

    /**
     * Returns the owner.
     *
     * @return ElementInterface|null
     */
    public function getOwner()
    {
        if ($this->_owner !== null) {
            return $this->_owner !== false ? $this->_owner : null;
        }

        if ($this->ownerId === null) {
            return null;
        }

        if (($this->_owner = Craft::$app->getElements()->getElementById($this->ownerId, null, $this->siteId)) === null) {
            // Be forgiving of invalid ownerId's in this case, since the field
            // could be in the process of being saved to a new element/site
            $this->_owner = false;

            return null;
        }

        return $this->_owner;
    }

    /**
     * Sets the owner
     *
     * @param ElementInterface $owner
     */
    public function setOwner(ElementInterface $owner)
    {
        $this->_owner = $owner;
    }

    /**
     * @inheritdoc
     */
    public function getContentTable(): string
    {
        return MetaPlugin::getInstance()->getField()->getContentTableName($this->getField());
    }

    /**
     * @inheritdoc
     */
    public function getFieldContext(): string
    {
        return FieldHelper::getContextById($this->fieldId);
    }


    /**
     * @inheritdoc
     */
    public static function getFieldsForElementsQuery(ElementQueryInterface $query)
    {

        if (isset($query->fieldId) and !empty($query->fieldId)) {

            // Get the field context
            $fieldContext = FieldHelper::getContextById($query->fieldId);

            // Get all fields (based on context);
            return Craft::$app->getFields()->getAllFields($fieldContext);

        }

        return [];

    }

    /**
     * @inheritdoc
     */
    public function getHasFreshContent(): bool
    {

        // Defer to the owner element
        $owner = $this->getOwner();

        return $owner ? $owner->getHasFreshContent() : false;

    }

    // Events
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     * @throws Exception
     */
    public function afterSave(bool $isNew)
    {

        // Get the record
        if (!$isNew) {
            $record = MetaRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid Meta Id: ' . $this->id);
            }
        } else {
            $record = new MetaRecord();
            $record->id = $this->id;
        }

        $record->fieldId = $this->fieldId;
        $record->ownerId = $this->ownerId;
        $record->ownerSiteId = $this->ownerSiteId;
        $record->sortOrder = $this->sortOrder;
        $record->save(false);

        parent::afterSave($isNew);

    }

    // Private Methods
    // =========================================================================

    /**
     * Returns the Meta field.
     *
     * @return FieldInterface|MetaField
     */
    private function getField()
    {

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        if (!$this->fieldId) {

            /** @var MissingField $newField */
            $missingField = new MissingField();

            /** @var MetaField $fallbackField */
            $fallbackField = $missingField->createFallback(MetaField::class);

            return $fallbackField;

        }

        return Craft::$app->getFields()->getFieldById($this->fieldId);

    }

}