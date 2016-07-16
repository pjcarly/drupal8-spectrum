<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Model\FieldRelationship;

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

            switch($fieldDefinition->getType())
            {
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
                    $dateValue = new \DateTime();
                    $dateValue->setTimestamp($attributeValue);
                    $dateValue = $dateValue->format('Y-m-d');
                  }
                  else if($fieldSettingsDatetimeType === 'datetime')
                  {
                    $dateValue = new \DateTime();
                    $dateValue->setTimestamp($attributeValue);
                    $dateValue = $dateValue->format('Y-m-d').'T'.$dateValue->format('H:i:s');
                  }
                }

                $this->entity->$fieldName->value = $dateValue;
                break;
              case 'entity_reference':
                // TODO
                break;
              case 'file':
                if(empty($attributeValue))
                {
                  $this->entity->$fieldName->target_id = null;
                }
                else
                {
                  $this->entity->$fieldName->target_id = $attributeValue->id;
                }
                break;
              case 'image':
                // TODO
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
                  $value['country_code'] = $attributeValue->country_code;
                  $value['administrative_area'] = $attributeValue->administrative_area;
                  $value['locality'] = $attributeValue->locality;
                  $value['dependent_locality'] = $attributeValue->dependent_locality;
                  $value['postal_code'] = $attributeValue->postal_code;
                  $value['sorting_code'] = $attributeValue->sorting_code;
                  $value['address_line1'] = $attributeValue->address_line1;
                  $value['address_line2'] = $attributeValue->address_line2;

                  $this->entity->$fieldName = $value;
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
              $relationship = static::getRelationshipByFieldName($fieldName);

              if(!empty($relationship))
              {
                // now the relationship exists, we'll do something different depending on the type of relationship
                if($relationship instanceof FieldRelationship)
                {
                  $relationshipField = $relationship->getField();
                  $relationshipColumn = $relationship->getColumn();

                  if(!empty($relationshipValue->data))
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
          } catch (\Drupal\spectrum\Exceptions\RelationshipNotDefinedException $e) {
            // ignore, the relationship passed doesn't exist
          }
        }
      }
      else if(in_array($key, static::$inlineRelationships))
      {
        // first we'll check if the relationship exists
        try
        {

          $relationship = $this::getRelationship($key);

          // now the relationship exists, we'll do something different depending on the type of relationship
          if($relationship instanceof ReferencedRelationship)
          {
            // With children, we'll have to loop every value, and deserialize it as well
            foreach($value as $deserializedChild)
            {
              // we'll get a child model by recursivly deserializing it as well
              $childModelDeserializer = new ModelDeserializer($relationship->modelType);
              $childModel = $childModelDeserializer->fromJsonApi($deserializedChild);

              if(!empty($childModel))
              {
                // we'll add the child model to our parent
                $this->put($relationship, $childModel);

                // and finally add the relationship to the found relationships, so we know what to save later
                $foundRelationships[$relationship->relationshipName] = $relationship;
              }
            }
          }
          else if ($relationship instanceof FieldRelationship)
          {
            //TODO: implement
          }

        } catch (\Drupal\spectrum\Exceptions\RelationshipNotDefinedException $e) {
          // ignore, the relationship passed doesn't exist
        }
      }
    }
  }
}
