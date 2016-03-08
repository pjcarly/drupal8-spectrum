<?php

namespace Drupal\spectrum\Serializer;

Use Drupal\spectrum\Utils\String;
use Drupal\spectrum\Model\ChildRelationship;
use Drupal\spectrum\Model\ParentRelationship;

class ModelDeserializer extends ModelSerializerBase
{
  function __construct($modelName)
  {
    parent::__construct($modelName);
    dump($this->modelName);
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
    $prettyFieldMapping = $this->getPrettyFieldsToFieldsMapping();

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
          if(array_key_exists($attributeKey, $prettyFieldMapping))
          {
            $prettyField = $prettyFieldMapping[$attributeKey];

            $model->entity->$prettyField->value = $attributeValue;
            $modelFound = true;
          }
        }
      }
      else if($key === 'relationships')
      {
        // TODO implement
      }
      else if(in_array($key, $modelName::$inlineRelationships))
      {
        dump($key);
        // first we'll check if the relationship exists
        try
        {
          $relationship = $model::getRelationship($key);

          // now the relationship exists, we'll do something different depending on the type of relationship
          if($relationship instanceof ChildRelationship)
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
          else if ($relationship instanceof ParentRelationship)
          {
            //TODO: implement
          }

        } catch (RelationshipNotDefinedException $e) {
          // ignore, the relationship passed doesn't exist
        }
      }
    }

    dump($model);
    dump($model->debugEntity());

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
