<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Models\File;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\spectrum\Exceptions\RelationshipNotDefinedException;

/**
 * This trait is used to abstract deserialization functionality
 */
trait ModelDeserializerMixin
{
  /**
   * This function will update the values of the entity based on the values of a jsonapi.org compliant object
   * based on the field type, necessary transforms to the drupal datastructure will be done.
   * Necessary checks will be done to make sure the user has permission to edit the field. In case no permission is granted, the field on the entity will not be updated
   *
   * @param \stdClass $deserialized jsonapi.org document
   * @return Model
   */
  public function applyChangesFromJsonAPIDocument(\stdClass $deserialized) : Model
  {
    // get helper variables
    $fieldNameMapping = static::getPrettyFieldsToFieldsMapping();

    // and now we'll loop over the different content of the deserialized object
    foreach($deserialized->data as $key => $value)
    {
      if($key === 'attributes')
      {
        // here we'll loop all the attributes in the json, and match them to existing attributes on the entity class
        foreach($value as $attributeKey => $attributeValue)
        {
          if(array_key_exists($attributeKey, $fieldNameMapping))
          {
            $fieldName = $fieldNameMapping[$attributeKey];
            $this->deserializeField($fieldName, $attributeValue);
          }
        }
      }
      else if($key === 'relationships')
      {
        foreach($value as $relationshipFieldName => $relationshipValue)
        {
          // first we'll check if the relationship exists
          try
          {
            if(array_key_exists($relationshipFieldName, $fieldNameMapping))
            {
              $fieldName = $fieldNameMapping[$relationshipFieldName];

              $this->deserializeRelationship($fieldName, $relationshipValue);
            }
          } catch (RelationshipNotDefinedException $e) {
            // ignore, the relationship passed doesn't exist
          }
        }
      }
    }

    return $this;
  }

  /**
   * Deserializes a Relationship into a Drupal entity
   *
   * @param string $fieldName The drupal fieldname for the entity reference
   * @param mixed $relationshipValue The value of a jsonapidocument related to the attribute
   * @return void
   */
  protected function deserializeRelationship(string $fieldName, $relationshipValue)
  {
    if(static::currentUserHasFieldPermission($fieldName, 'edit')) // Only allow fields the user has access to
    {
      $relationship = static::getRelationshipByFieldName($fieldName);

      if(!empty($relationship))
      {
        // now the relationship exists, we'll do something different depending on the type of relationship
        if($relationship instanceof FieldRelationship)
        {
          $relationshipField = $relationship->getField();
          $relationshipColumn = $relationship->getColumn();

          if($relationship->fieldCardinality !== 1)
          {
            // This is a multi-reference field
            // We need to set an array, instead of a single field column
            $this->entity->$relationshipField = [];

            if(!empty($relationshipValue->data) && is_array($relationshipValue->data))
            {
              foreach($relationshipValue->data as $singleRelationshipValue)
              {
                $this->entity->$relationshipField[] = [$relationshipColumn => $singleRelationshipValue->id];
              }
            }
          }
          else
          {
            if(empty($relationshipValue->data))
            {
              $this->entity->$relationshipField->$relationshipColumn = null;
            }
            else
            {
              $this->entity->$relationshipField->$relationshipColumn = $relationshipValue->data->id;
            }
          }
        }
        else if ($relationship instanceof ReferencedRelationship)
        {
          // TODO: make this work with entity reference multi-field
        }
      }
    }
  }

  /**
   * Serializes the attribute value into a field
   *
   * @param string $fieldName The drupal field name
   * @param mixed $attributeValue The json api attribute value
   * @return void
   */
  protected function deserializeField(string $fieldName, $attributeValue)
  {
    $fieldDefinition = static::getFieldDefinition($fieldName);
    $fieldCardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();

    if(static::currentUserHasFieldPermission($fieldName, 'edit')) // Only allow fields the user has access to
    {
      switch($fieldDefinition->getType())
      {
        case 'boolean':
          $this->entity->$fieldName->value = $attributeValue ? '1' : '0'; // cannot be stricly typed, drupal uses true/false as '1' and '0' interchangeably
          break;
        case 'geolocation':
          $this->entity->$fieldName->lat = isset($attributeValue->lat) ? $attributeValue->lat : null;
          $this->entity->$fieldName->lng = isset($attributeValue->lng) ? $attributeValue->lng : null;
          break;
        case 'datetime':
          $dateValue = null;

          if(!empty($attributeValue))
          {
            // We must figure out if this is a Date field or a datetime field
            // lets get the meta information of the field
            $fieldSettingsDatetimeType = $fieldDefinition->getItemDefinition()->getSettings()['datetime_type'];
            if($fieldSettingsDatetimeType === 'date')
            {
              $dateValue = new \DateTime($attributeValue);
              $dateValue = $dateValue->format(DateTimeItemInterface::DATE_STORAGE_FORMAT);
            }
            else if($fieldSettingsDatetimeType === 'datetime')
            {
              $dateValue = new \DateTime($attributeValue);
              $dateValue = $dateValue->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
            }
          }

          $this->entity->$fieldName->value = $dateValue;
          break;
        case 'file':
        case 'image':
          $valueToSet = null;

          if($fieldCardinality === 1)
          {
            if(isset($attributeValue->id) && isset($attributeValue->hash))
            {
              $fileModel = File::forgeById($attributeValue->id);
              $fileModel = new File($fileModel->entity); // TODO File/Image model fix
              // We must be sure that the hash provided in the deserialization, matches the file entity in the database
              // That way no unauthorized file linking can occur
              if($fileModel->getId() === $attributeValue->id && $fileModel->getHash() === $attributeValue->hash)
              {
                $valueToSet = $attributeValue->id;
              }
            }

            $this->entity->$fieldName->target_id = $valueToSet;
          }
          else
          {
            $valueToSet = [];
            if(is_array($attributeValue))
            {
              $fileIds = [];
              foreach($attributeValue as $singleAttributeValue)
              {
                if(isset($singleAttributeValue->id))
                {
                  $fileIds[] = $singleAttributeValue->id;
                }
              }

              if(!empty($fileIds))
              {
                $filesCollection = Collection::forgeByIds(File::class, $fileIds);

                if(!$filesCollection->isEmpty)
                {
                  foreach($attributeValue as $singleAttributeValue)
                  {
                    if(isset($singleAttributeValue->id) && $filesCollection->containsKey($singleAttributeValue->id))
                    {
                      $fileModel = $filesCollection->getModel($singleAttributeValue->id);

                      // We must be sure that the hash provided in the deserialization, matches the file entity in the database
                      // That way no unauthorized file linking can occur
                      if($fileModel->getId() === $singleAttributeValue->id && $fileModel->getHash() === $singleAttributeValue->hash)
                      {
                        $valueToSet[] = ['target_id' => $singleAttributeValue->id];
                      }
                    }
                  }
                }
              }
            }

            $this->entity->$fieldName = $valueToSet;
          }
          break;
        case 'json':
          $this->entity->$fieldName->value = json_encode($attributeValue);
          break;
        case 'link':
          $this->entity->$fieldName->uri = $attributeValue;
          break;
        case 'address':
          if(empty($attributeValue))
          {
            $this->entity->$fieldName->country_code = null;
            $this->entity->$fieldName->administrative_area = null;
            $this->entity->$fieldName->locality = null;
            $this->entity->$fieldName->dependent_locality = null;
            $this->entity->$fieldName->postal_code = null;
            $this->entity->$fieldName->sorting_code = null;
            $this->entity->$fieldName->address_line1 = null;
            $this->entity->$fieldName->address_line2 = null;
          }
          else
          {
            $value = [];
            $value['country_code'] = $attributeValue->{'countryCode'};
            $value['administrative_area'] = $attributeValue->{'administrativeArea'};
            $value['locality'] = $attributeValue->{'locality'};
            $value['dependent_locality'] = $attributeValue->{'dependentLocality'};
            $value['postal_code'] = $attributeValue->{'postalCode'};
            $value['sorting_code'] = $attributeValue->{'sortingCode'};
            $value['address_line1'] = $attributeValue->{'addressLine1'};
            $value['address_line2'] = $attributeValue->{'addressLine2'};

            $this->entity->$fieldName = $value;
          }

          break;
        case 'entity_reference':
          // Entity references are generally deserialized through the relationships hash,
          // Except for currency, a currency is passed as a value (the ISO currency code)
          // And since in our system the ID of the currency is the iso currency code, we use that instead
          $fieldObjectSettings = $fieldDefinition->getSettings();
          if(!empty($fieldObjectSettings) && array_key_exists('target_type', $fieldObjectSettings) && $fieldObjectSettings['target_type'] === 'currency')
          {
            $this->entity->$fieldName->target_id = $attributeValue;
          }
          break;
        case 'created':
        case 'changed':
          // Do nothing, internal fields
          break;
        default:
          if($fieldCardinality !== 1)
          {
            // More than 1 value allowed in the field
            $this->entity->$fieldName = [];
            if(is_array($attributeValue))
            {
              foreach($attributeValue as $singleAttributeValue)
              {
                $this->entity->$fieldName[] = $singleAttributeValue;
              }
            }
          }
          else
          {
            $this->entity->$fieldName->value = $attributeValue;
          }

          break;
      }
    }
  }
}
