<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Model\FieldRelationship;

class ModelDeserializer extends ModelSerializerBase
{
  function __construct($modelName)
  {
    parent::__construct($modelName);
  }

  function deserialize($type, $serialized)
  {
    switch($type)
    {
      case 'json-api':
      default:
        $deserialized = json_decode($serialized);
        return $this->fromJsonApi($deserialized);
      break;
    }
  }

  public function fromJsonApi($deserialized)
  {
    // get helper variables
    $modelName = $this->modelName;
    $fieldNameMapping = $this->getPrettyFieldsToFieldsMapping();
    //$fieldDefinitions = $model->getFieldDefinitions();

    // we'll keep track of some flags
    $modelFound = false;
    $foundRelationships = array();

    // create a new $model
    $model = $modelName::createNew();

    // and now we'll loop over the different content of the deserialized object
    foreach($deserialized->data as $key => $value)
    {
      if($key === 'id')
      {
        // we found the id, lets get the name of the id field on the entity and fill it
        $idField = $model::$idField;
        $model->entity->$idField->value = $value;
        $modelFound = true;
      }
      else if($key === 'attributes')
      {
        // here we'll loop all the attributes in the json, and match them to existing attributes on the entity class
        foreach($value as $attributeKey => $attributeValue)
        {
          if(array_key_exists($attributeKey, $fieldNameMapping))
          {
            $fieldName = $fieldNameMapping[$attributeKey];
            $fieldDefinition = $modelName::getFieldDefinition($fieldName);

            switch($fieldDefinition->getType())
            {
              case 'geolocation':
                $model->entity->$fieldName->lat = $attributeValue->lat;
                $model->entity->$fieldName->lng = $attributeValue->lng;
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

                $model->entity->$fieldName->value = $dateValue;
                break;
              default:
                $model->entity->$fieldName->value = $attributeValue;
                break;
            }

            $modelFound = true;
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
              $relationship = $model::getRelationshipByFieldName($fieldName);

              if(!empty($relationship))
              {
                // now the relationship exists, we'll do something different depending on the type of relationship
                if($relationship instanceof FieldRelationship)
                {
                  $relationshipField = $relationship->getField();
                  $relationshipColumn = $relationship->getColumn();

                  if(!empty($relationshipValue->data))
                  {
                    $model->entity->$relationshipField->$relationshipColumn = $relationshipValue->data->id;
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
      else if(in_array($key, $modelName::$inlineRelationships))
      {
        // first we'll check if the relationship exists
        try
        {

          $relationship = $model::getRelationship($key);

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
                $model->put($relationship, $childModel);

                // and finally add the relationship to the found relationships, so we know what to save later
                $foundRelationships[$relationship->relationshipName] = $relationship;
                $modelFound = true;
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

    if($modelFound)
    {
      return $model;
    }
    else
    {
      return null;
    }
  }


}
