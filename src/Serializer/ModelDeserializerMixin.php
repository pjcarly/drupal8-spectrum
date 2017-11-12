<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Serializer\JsonApiRootNode;

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

            if(static::currentUserHasFieldPermission($fieldName)) // Only allow fields the user has access to
            {
              switch($fieldDefinition->getType())
              {
                case 'boolean':
                  $this->entity->$fieldName->value = empty($attributeValue) ? '0' : '1';
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
                      $dateValue = $dateValue->format('Y-m-d');
                    }
                    else if($fieldSettingsDatetimeType === 'datetime')
                    {
                      $dateValue = new \DateTime($attributeValue);
                      $dateValue = $dateValue->format('Y-m-d').'T'.$dateValue->format('H:i:s');
                    }
                  }

                  $this->entity->$fieldName->value = $dateValue;
                  break;
                case 'file':
                case 'image':
                  // TODO: add hash check
                  if(isset($attributeValue->id))
                  {
                    $this->entity->$fieldName->target_id = $attributeValue->id;
                  }
                  else
                  {
                    $this->entity->$fieldName->target_id = null;
                  }
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
                    $value['country_code'] = $attributeValue->{'country-code'};
                    $value['administrative_area'] = $attributeValue->{'administrative-area'};
                    $value['locality'] = $attributeValue->{'locality'};
                    $value['dependent_locality'] = $attributeValue->{'dependent-locality'};
                    $value['postal_code'] = $attributeValue->{'postal-code'};
                    $value['sorting_code'] = $attributeValue->{'sorting-code'};
                    $value['address_line1'] = $attributeValue->{'address-line1'};
                    $value['address_line2'] = $attributeValue->{'address-line2'};

                    $this->entity->$fieldName = $value;
                  }

                  break;
                case 'entity_reference':
                  if($fieldName === 'field_currency')
                  {
                    $this->entity->$fieldName->target_id = $attributeValue;
                  }
                  break;
                case 'created':
                case 'changed':
                  // Do nothing, internal fields
                  break;
                default:
                  $this->entity->$fieldName->value = $attributeValue;
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

              if(static::currentUserHasFieldPermission($fieldName)) // Only allow fields the user has access to
              {
                $relationship = static::getRelationshipByFieldName($fieldName);

                if(!empty($relationship))
                {
                  // now the relationship exists, we'll do something different depending on the type of relationship
                  if($relationship instanceof FieldRelationship)
                  {
                    $relationshipField = $relationship->getField();
                    $relationshipColumn = $relationship->getColumn();

                    if(empty($relationshipValue->data))
                    {
                      $this->entity->$relationshipField->$relationshipColumn = null;
                    }
                    else
                    {
                      $this->entity->$relationshipField->$relationshipColumn = $relationshipValue->data->id;
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
