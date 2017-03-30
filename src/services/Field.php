<?php

/**
 * @copyright  Copyright (c) Flipbox Digital Limited
 * @license    https://flipboxfactory.com/software/meta/license
 * @link       https://www.flipboxfactory.com/software/meta/
 */

namespace flipbox\meta\services;

use Craft;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\db\Query;
use craft\helpers\StringHelper;
use flipbox\meta\elements\db\Meta as MetaQuery;
use flipbox\meta\elements\Meta as MetaElement;
use flipbox\meta\fields\Meta as MetaField;
use flipbox\meta\helpers\Field as FieldHelper;
use flipbox\meta\Plugin as MetaPlugin;
use yii\base\Component;

/**
 * @package flipbox\meta\services
 * @author Flipbox Factory <hello@flipboxfactory.com>
 * @since 1.0.0
 */
class Field extends Component
{

    /**
     * @param MetaField $field
     * @param ElementInterface $element
     * @return bool
     */
    public function beforeElementDelete(MetaField $field, ElementInterface $element): bool
    {

        // Delete any meta elements that belong to this element(s)
        foreach (Craft::$app->getSites()->getAllSiteIds() as $siteId) {
            $query = MetaElement::find();
            $query->status(null);
            $query->enabledForSite(false);
            $query->fieldId($field->id);
            $query->siteId($siteId);
            $query->owner($element);

            /** @var MetaElement $meta */
            foreach ($query as $meta) {
                MetaPlugin::getInstance()->getMeta()->delete($meta);
            }

        }

        return true;

    }

    /**
     * @param MetaField $field
     * @param ElementInterface $owner
     * @return bool
     * @throws \Exception
     * @throws \yii\db\Exception
     */
    public function afterElementSave(MetaField $field, ElementInterface $owner)
    {

        /** @var Element $owner */

        /** @var MetaQuery $query */
        $query = $owner->getFieldValue($field->handle);

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {

            // Do localization
            $this->applyFieldLocalizationSetting($owner, $field, $query);

            $metaIds = [];

            /** @var Meta $element */
            foreach ($query as $meta) {

                // Set owner based attributes
                $meta->ownerId = $owner->id;
                $meta->ownerSiteId = ($field->localize ? $owner->siteId : null);

                // Save
                Craft::$app->getElements()->saveElement($meta, false);

                $metaIds[] = $meta->id;

            }

            // Delete any elements that shouldn't be there anymore
            /** @var MetaQuery $deleteElementsQuery */
            $deleteElementsQuery = MetaElement::find()
                ->ownerId($owner->getId())
                ->fieldId($field->id)
                ->andWhere(['not', ['elements.id' => $metaIds]]);

            if ($field->localize) {
                $deleteElementsQuery->ownerSiteId = $owner->siteId;
            } else {
                $deleteElementsQuery->siteId = $owner->siteId;
            }

            foreach ($deleteElementsQuery as $deleteElement) {
                Craft::$app->getElements()->deleteElement($deleteElement);
            }

            // Success
            $transaction->commit();

        } catch (\Exception $e) {

            // Revert
            $transaction->rollback();

            throw $e;

        }

        return true;

    }

    /**
     * Applies the field's translation setting to a set of elements.
     *
     * @param ElementInterface $owner
     * @param MetaField $field
     * @param MetaQuery $query
     *
     * @return void
     */
    private function applyFieldLocalizationSetting(ElementInterface $owner, MetaField $field, MetaQuery $query)
    {

        /** @var Element $owner */

        // Does it look like any work is needed here?
        $applyNewLocalizationSetting = false;

        foreach ($query as $meta) {
            if ($meta->id && (
                    ($field->localize && !$meta->ownerSiteId) ||
                    (!$field->localize && $meta->ownerSiteId)
                )
            ) {
                $applyNewLocalizationSetting = true;
                break;
            }
        }

        if (!$applyNewLocalizationSetting) {
            // All good
            return;
        }

        // Get all of the elements for this field/owner that use the other locales, whose ownerLocale attribute is set
        // incorrectly
        $elementsInOtherSites = [];

        $query = MetaElement::find()
            ->fieldId($field->id)
            ->ownerId($owner->getId())
            ->status(null)
            ->enabledForSite(false)
            ->limit(null);

        if ($field->localize) {
            $query->ownerSiteId(':empty:');
        }

        foreach (Craft::$app->getI18n()->getSiteLocaleIds() as $siteId) {
            if ($siteId === $owner->siteId) {
                continue;
            }

            $query->siteId($siteId);

            if (!$field->localize) {
                $query->ownerSiteId($siteId);
            }

            $elementsInOtherSite = $query->all();

            if (!empty($elementsInOtherSite)) {
                $elementsInOtherSites[$siteId] = $elementsInOtherSite;
            }
        }

        if (empty($elementsInOtherSites)) {
            return;
        }

        if ($field->localize) {
            $newElementIds = [];

            // Duplicate the other-site elements so each site has their own unique set of elements
            foreach ($elementsInOtherSites as $siteId => $elementsInOtherSite) {
                foreach ($elementsInOtherSite as $elementInOtherSite) {
                    /** @var MetaElement $elementInOtherSite */
                    $originalElementId = $elementInOtherSite->id;

                    $elementInOtherSite->id = null;
                    $elementInOtherSite->contentId = null;
                    $elementInOtherSite->ownerSiteId = (int)$siteId;
                    Craft::$app->getElements()->saveElement($elementInOtherSite, false);

                    $newElementIds[$originalElementId][$siteId] = $elementInOtherSite->id;
                }
            }

            // Duplicate the relations, too.  First by getting all of the existing relations for the original
            // elements
            $relations = (new Query())
                ->select([
                    'fieldId',
                    'sourceId',
                    'sourceSiteId',
                    'targetId',
                    'sortOrder'
                ])
                ->from(['{{%relations}}'])
                ->where(['sourceId' => array_keys($newElementIds)])
                ->all();

            if (!empty($relations)) {
                // Now duplicate each one for the other sites' new elements
                $rows = [];

                foreach ($relations as $relation) {
                    $originalElementId = $relation['sourceId'];

                    // Just to be safe...
                    if (isset($newElementIds[$originalElementId])) {
                        foreach ($newElementIds[$originalElementId] as $siteId => $newElementId) {
                            $rows[] = [
                                $relation['fieldId'],
                                $newElementId,
                                $relation['sourceSiteId'],
                                $relation['targetId'],
                                $relation['sortOrder']
                            ];
                        }
                    }
                }

                Craft::$app->getDb()->createCommand()
                    ->batchInsert(
                        'relations',
                        [
                            'fieldId',
                            'sourceId',
                            'sourceSiteId',
                            'targetId',
                            'sortOrder'
                        ],
                        $rows)
                    ->execute();
            }
        } else {
            // Delete all of these elements
            $deletedElementIds = [];

            foreach ($elementsInOtherSites as $elementsInOtherSite) {
                foreach ($elementsInOtherSite as $elementInOtherSite) {
                    // Have we already deleted this element?
                    if (in_array($elementInOtherSite->id, $deletedElementIds, false)) {
                        continue;
                    }

                    Craft::$app->getElements()->deleteElement($elementInOtherSite);
                    $deletedElementIds[] = $elementInOtherSite->id;

                }

            }

        }
    }


    /**
     * Returns the content table name for a given Meta field.
     *
     * @param MetaField $metaField The Meta field.
     * @param bool $useOldHandle Whether the method should use the field’s old handle when determining the table
     *                                  name (e.g. to get the existing table name, rather than the new one).
     *
     * @return string|false The table name, or `false` if $useOldHandle was set to `true` and there was no old handle.
     */
    public function getContentTableName(MetaField $metaField, $useOldHandle = false)
    {

        $name = '';

        if ($useOldHandle) {
            if (!$metaField->oldHandle) {
                return false;
            }

            $handle = $metaField->oldHandle;
        } else {
            $handle = $metaField->handle;
        }

        $name = StringHelper::toLowerCase($handle) . $name;

        return FieldHelper::getContentTableName($name);

    }

}