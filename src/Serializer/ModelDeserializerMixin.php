<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Models\File;

trait ModelDeserializerMixin
{
  public function applyChangesFromJsonAPIDocument($deserialized)
  {
    // get helper variables
    $fieldNameMapping = static::getPrettyFieldsToFieldsMapping();

    // we'll keep track of some flags
    $foundRelationships = array();

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
                  $this->entity->$fieldName->lat = $attributeValue->lat;
                  $this->entity->$fieldName->lng = $attributeValue->lng;
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
                      $dateValue = $dateValue->format(DATETIME_DATE_STORAGE_FORMAT);
                    }
                    else if($fieldSettingsDatetimeType === 'datetime')
                    {
                      $dateValue = new \DateTime($attributeValue);
                      $dateValue = $dateValue->format(DATETIME_DATETIME_STORAGE_FORMAT);
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
                        $filesCollection = Collection::forgeByIds('\Drupal\spectrum\Models\File', $fileIds);

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
                    $value = array();
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
          } catch (\Drupal\spectrum\Exceptions\RelationshipNotDefinedException $e) {
            // ignore, the relationship passed doesn't exist
          }
        }
      }
    }
  }
}
