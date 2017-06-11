<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\elements\db;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db as DbHelper;
use craft\models\Site;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\fields\Meta as MetaField;
use flipbox\meta\helpers\Field as FieldHelper;
use flipbox\meta\Meta as MetaPlugin;
use flipbox\meta\records\Meta as MetaRecord;
use yii\base\Exception;

/**
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 *
 * @property string|string[]|Site $ownerSite The handle(s) of the site(s) that the owner element should be in
 *
 * @method MetaElement[]|array all($db = null)
 * @method MetaElement|null one($db = null)
 */
class Meta extends ElementQuery
{

    /**
     * The field ID(s) that the resulting Meta must belong to.
     *
     * @var integer|integer[]
     */
    public $fieldId;

    /**
     * The owner element ID(s) that the resulting Meta must belong to.
     *
     * @var int|int[]|null
     */
    public $ownerId;

    /**
     * The site ID that the resulting Meta must have been defined in, or ':empty:' to find
     * elements without an owner site ID.
     *
     * @var int|string|null
     */
    public $ownerSiteId;

    /**
     * @inheritdoc
     */
    public function __construct($elementType, array $config = [])
    {
        // Default orderBy
        if (!isset($config['orderBy'])) {
            $config['orderBy'] = 'meta.sortOrder';
        }

        parent::__construct($elementType, $config);
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'ownerSite':
                $this->ownerSite($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * Sets the [[fieldId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function fieldId($value)
    {
        $this->fieldId = $value;

        return $this;
    }

    /**
     * Sets the [[ownerId]] property.
     *
     * @param int|int[]|null $value The property value
     *
     * @return static self reference
     */
    public function ownerId($value)
    {
        $this->ownerId = $value;

        return $this;
    }

    /**
     * Sets the [[ownerSiteId]] and [[siteId]] properties.
     *
     * @param int|string|null $value The property value
     *
     * @return static self reference
     */
    public function ownerSiteId($value)
    {
        $this->ownerSiteId = $value;

        if ($value && strtolower($value) !== ':empty:') {
            // A meta will never exist in a site that is different than its ownerSiteId,
            // so let's set the siteId param here too.
            $this->siteId = (int)$value;
        }

        return $this;
    }

    /**
     * Sets the [[ownerSiteId]] property based on a given site(s)’s handle(s).
     *
     * @param string|string[]|Site $value The property value
     *
     * @return static self reference
     * @throws Exception if $value is an invalid site handle
     */
    public function ownerSite($value)
    {
        if ($value instanceof Site) {
            $this->ownerSiteId($value->id);
        } else {
            $site = Craft::$app->getSites()->getSiteByHandle($value);

            if (!$site) {
                throw new Exception('Invalid site handle: ' . $value);
            }

            $this->ownerSiteId($site->id);
        }

        return $this;
    }

    /**
     * Sets the [[ownerId]] and [[ownerSiteId]] properties based on a given element.
     *
     * @param ElementInterface $owner The owner element
     *
     * @return static self reference
     */
    public function owner(ElementInterface $owner)
    {
        /** @var Element $owner */
        $this->ownerId = $owner->id;
        $this->ownerSiteId = $owner->siteId;

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        $this->joinElementTable(MetaRecord::tableAlias());

        // Figure out which content table to use
        $this->contentTable = null;

        if (!$this->fieldId && $this->id && is_numeric($this->id)) {
            $this->fieldId = (new Query())
                ->select('fieldId')
                ->from(MetaRecord::tableName())
                ->where(['id' => $this->id])
                ->scalar();
        }

        if ($this->fieldId && is_numeric($this->fieldId)) {
            /** @var MetaField $field */
            $field = Craft::$app->getFields()->getFieldById($this->fieldId);

            if ($field) {
                $this->contentTable = MetaPlugin::getInstance()->getField()->getContentTableName($field);
            }
        }

        $this->query->select([
            MetaRecord::tableAlias() . '.fieldId',
            MetaRecord::tableAlias() . '.ownerId',
            MetaRecord::tableAlias() . '.sortOrder',
        ]);

        if ($this->fieldId) {
            $this->subQuery->andWhere(DbHelper::parseParam(MetaRecord::tableAlias() . '.fieldId', $this->fieldId));
        }

        if ($this->ownerId) {
            $this->subQuery->andWhere(DbHelper::parseParam(MetaRecord::tableAlias() . '.ownerId', $this->ownerId));
        }

        if ($this->ownerSiteId) {
            $this->subQuery->andWhere(DbHelper::parseParam(MetaRecord::tableAlias() . '.siteId', $this->ownerSiteId));
        }

        return parent::beforePrepare();
    }

    /**
     * @inheritdoc
     */
    protected function customFields(): array
    {
        return Craft::$app->getFields()->getAllFields(
            FieldHelper::getContextById($this->fieldId)
        );
    }
}
