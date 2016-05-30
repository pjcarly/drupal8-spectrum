<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\EntityQuery;
use Drupal\spectrum\Query\BundleQuery;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Exceptions\InvalidTypeException;
use Drupal\spectrum\Exceptions\NotImplementedException;
use Drupal\spectrum\Exceptions\ModelClassNotDefinedException;
use Drupal\spectrum\Exceptions\InvalidRelationshipTypeException;
use Drupal\spectrum\Exceptions\RelationshipNotDefinedException;
Use Drupal\spectrum\Utils\StringUtils;

use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiNode;
use Drupal\spectrum\Serializer\JsonApiDataNode;

abstract class Model
{
  public static $entityType;
  public static $bundle;
  public static $idField;

  public static $modelClassMapping = null;
  public static $relationships = array();
  public static $keyIndex = 1;

  public $entity;
  public $key;

  public $relatedViaFieldOnEntity = array();
  public $relatedViaFieldOnExternalEntity = array();

  // json-api options
  public static $inlineRelationships = array();

  public function __construct($entity)
  {
    $this->entity = $entity;
    $id = $this->getId();

    if(isset($id))
    {
      $this->key = $id;
    }
    else
    {
      $this->key = static::getNextKey();
    }
  }

  public function save($relationshipName = NULL)
  {
    if(empty($relationshipName))
    {
      $isNew = empty($this->getId());
      $this->entity->save();

      if($isNew)
      {
        $this->setFieldForReferencedRelationships();
      }
    }
    else
    {
      $this->get($relationshipName)->save();
    }
  }

  public function validate($relationshipName = NULL)
  {
    if(empty($relationshipName))
    {
      return new Validation($this);
    }
    else
    {
      return $this->get($relationshipName)->validate();
    }
  }

  private function setFieldForReferencedRelationships()
  {
    // This method sets the field with newly created ID upon inserting a new record
    // This is important for when you have multiple related models in memory who haven't been inserted
    // and just have are just related in memory, by setting the ID we know how to relate them in the DB
    $relationships = static::getRelationships();
    foreach($relationships as $relationship)
    {
      if($relationship instanceof ReferencedRelationship)
      {
        $referencedRelationship = $this->get($relationship->relationshipName);
        if(!empty($referencedRelationship))
        {
          if($referencedRelationship instanceof Collection)
          {
            foreach($referencedRelationship->models as $referencedModel)
            {
              $referencedModel->put($relationship->fieldRelationship, $this);
            }
          }
          else if($referencedRelationship instanceof Model)
          {
            $referencedRelationship->put($relationship->fieldRelationship, $this);
          }
        }
      }
    }
  }

  public function fetch($relationshipName)
  {
    $lastRelationshipNameIndex = strrpos($relationshipName, '.');

    if(empty($lastRelationshipNameIndex)) // relationship name without extra relationships
    {
      $relationship = static::getRelationship($relationshipName);

      $relationshipQuery = $relationship->getRelationshipQuery();
      $relationshipCondition = $relationship->getCondition();

      if($relationship instanceof FieldRelationship)
      {
        $fieldId = $this->getFieldId($relationship);

        if(!empty($fieldId))
        {
          // we start of by checking for multiple or single values allowed
          // in case of a single, we'll just put a single Model
          // else we'll put a collection of models

          if(is_array($fieldId)) // multiple values
          {
            $relationshipCondition->value = $fieldId;
            $relationshipCondition->operator = 'IN';

            $relationshipQuery->addCondition($relationshipCondition);
            $referencedEntities = $relationshipQuery->fetch();

            if(!empty($referencedEntities))
            {
              $referencedModelType = null;
              $referencedRelationship = null; // the inverse relationship
              foreach($referencedEntities as $referencedEntity)
              {
                $referencedModel = null;
                if($relationship->isPolymorphic || empty($referencedModelType))
                {
                  // if the relationship is polymorphic we can get multiple bundles, so we must define the modeltype based on the bundle and entity of the current looping entity
                  // or if the related modeltype isn't set yet, we must set it once
                  $referencedEntityType = $referencedEntity->getEntityTypeId();
                  $referencedEntityBundle = $referencedEntity->type->target_id;
                  $referencedModelType = Model::getModelClassForEntityAndBundle($referencedEntityType, $referencedEntityBundle);

                  // we must also find the inverse relationship to put the current model on
                  $referencedRelationship = $referencedModelType::getReferencedRelationshipForFieldRelationship($relationship);
                }

                // now that we have a model, lets put them one by one
                $referencedModel = $referencedModelType::forge($referencedEntity);
                $this->put($relationship, $referencedModel);

                // And finally if we found an inverse relationship, lets put (this) on the inverse (defining an inverse is optional, so we can just as easily find no inverses)
                if(!empty($referencedRelationship))
                {
                  $referencedModel->put($referencedRelationship, $this);
                }
              }
            }
          }
          else // single value
          {
            $relationshipCondition->value = $fieldId;
            $relationshipCondition->operator = '=';

            $relationshipQuery->addCondition($relationshipCondition);
            $referencedEntity = $relationshipQuery->fetchSingle();

            if(!empty($referencedEntity))
            {
              // if the relationship is polymorphic we can get multiple bundles, so we must define the modeltype based on the bundle and entity of the fetched entity
              $referencedEntityType = $referencedEntity->getEntityTypeId();
              $referencedEntityBundle = $referencedEntity->type->target_id;
              $referencedModelType = Model::getModelClassForEntityAndBundle($referencedEntityType, $referencedEntityBundle);

              // now that we have a model, lets put them one by one
              $referencedModel = $referencedModelType::forge($referencedEntity);

              $this->put($relationship, $referencedModel);

              // we must also find the inverse relationship to put the current model on
              $referencedRelationship = $referencedModelType::getReferencedRelationshipForFieldRelationship($relationship);

              // And finally if we found an inverse relationship, lets put (this) on the inverse (defining an inverse is optional, so we can just as easily find no inverses)
              if(!empty($referencedRelationship))
              {
                $referencedModel->put($referencedRelationship, $this);
              }
            }
          }
        }
      }
      else if($relationship instanceof ReferencedRelationship)
      {
        $id = $this->getId();
        if(!empty($id)) // fetching referenced relationships for new records is not possible
        {
          $relationshipCondition->value = array($id);
          $relationshipQuery->addCondition($relationshipCondition);
          $referencingEntities = $relationshipQuery->fetch();

          if(!empty($referencingEntities))
          {
            $referencingModelType = null;
            foreach($referencingEntities as $referencingEntity)
            {
              $referencingModel = null;
              if(empty($referencingModelType))
              {
                // if the referencing modeltype isn't set yet, we must set it once
                $referencingEntityType = $referencingEntity->getEntityTypeId();
                $referencingEntityBundle = $referencingEntity->type->target_id;
                $referencingModelType = Model::getModelClassForEntityAndBundle($referencingEntityType, $referencingEntityBundle);
              }

              // now that we have a model, lets put them one by one
              $referencingModel = $referencingModelType::forge($referencingEntity);
              $this->put($relationship, $referencingModel);
              $referencingModel->put($relationship->fieldRelationship, $this);
            }
          }
        }
      }
    }
    else
    {
      $secondToLastRelationshipName = substr($relationshipName, 0, $lastRelationshipNameIndex);
      $resultCollection = $this->get($secondToLastRelationshipName);
      $lastRelationshipName = substr($relationshipName, $lastRelationshipNameIndex+1);
      $resultCollection->fetch($lastRelationshipName);
    }
  }

  public function getModelName()
  {
    return get_class($this);
  }

  public function get($relationship)
  {
    $firstRelationshipNameIndex = null;
    if(is_string($relationship))
    {
      $firstRelationshipNameIndex = strpos($relationship, '.');
    }

    if(empty($firstRelationshipNameIndex))
    {
      if(!$relationship instanceof Relationship)
      {
        $relationship = static::getRelationship($relationship);
      }

      if($relationship instanceof FieldRelationship)
      {
        if(array_key_exists($relationship->relationshipName, $this->relatedViaFieldOnEntity))
        {
          return $this->relatedViaFieldOnEntity[$relationship->relationshipName];
        }
      }
      else if($relationship instanceof ReferencedRelationship)
      {
        if(array_key_exists($relationship->relationshipName, $this->relatedViaFieldOnExternalEntity))
        {
          return $this->relatedViaFieldOnExternalEntity[$relationship->relationshipName];
        }
      }
    }
    else
    {
      $firstRelationshipName = substr($relationshipName, 0,  $firstRelationshipNameIndex);
      $firstRelationshipGet = $this->get($firstRelationshipName);
      $newRelationshipName = substr($relationshipName, $firstRelationshipNameIndex+1);

      return $firstRelationshipGet->get($newRelationshipName);
    }

    return null;
  }

  public function getId()
  {
    $idField = static::$idField;
    return $this->entity->$idField->value;
  }

  public function getFieldId($relationship)
  {
    if($relationship instanceof FieldRelationship)
    {
      $entity = $this->entity;
      $field = $relationship->getField();
      $column = $relationship->getColumn();

      if($relationship->isSingle) // meaning the field can only contain 1 reference
      {
        return empty($entity->$field->$column) ? null : $entity->$field->$column;
      }
      else
      {
        $returnValue = array();
        foreach($entity->$field->getValue() as $fieldValue)
        {
          $returnValue[] = $fieldValue[$column];
        }

        return $returnValue;
      }
    }
    else
    {
      throw new InvalidRelationshipTypeException('Only Field relationships allowed');
    }
  }

  public function isParentOf($model, $relationship)
  {
    $fieldId = $model->getFieldId($relationship);
    $id = $this->getId();

    // we must consider the 2 cases where either a field Id can be an array (in case of multiple references per field)
    // or a single id, in case only 1 reference per field allowed
    return !empty($fieldId) && !empty($id) && (is_array($fieldId) && in_array($id, $fieldId) || !is_array($fieldId) && $fieldId === $id);
  }

  public function put($relationship, $objectToPut)
  {
    if($objectToPut != null && ($objectToPut instanceof Model || $objectToPut instanceof Collection))
    {
      if(is_string($relationship)) // we only have the relationship name
      {
        $relationship = static::getRelationship($relationship);
      }

      if($relationship instanceof FieldRelationship)
      {
        $relationshipField = $relationship->getField();
        $relationshipColumn = $relationship->getColumn();

        if($relationship->isMultiple)
        {
          // In case we have a collection we want to put, lets loop over de models, and add them model per model
          if($objectToPut instanceof Collection)
          {
            foreach($objectToPut as $model)
            {
              $this->put($relationshp, $model);
            }
          }
          else if($objectToPut instanceof Model)
          {
            // In case we have a model, it means we have to add it to the collection, that potentially doesn't exist yet
            // lets watch out, that the relationship can be polymorphic to create the correct collection if needed
            if(!array_key_exists($relationship->relationshipName, $this->relatedViaFieldOnEntity))
            {
              if($relationship->isPolymorphic)
              {
                $this->relatedViaFieldOnEntity[$relationship->relationshipName] = PolymorphicCollection::forge(null);
              }
              else
              {
                $this->relatedViaFieldOnEntity[$relationship->relationshipName] = Collection::forge($relationship->modelType);
              }
            }

            // we put the model on the collection
            $this->relatedViaFieldOnEntity[$relationship->relationshipName]->put($objectToPut);
            // and also append the entity field with the value (append because their can be multiple items)
            $objectToPutId = $objectToPut->getId();
            if(!empty($objectToPutId))
            {
              $this->entity->$relationshipField->appendItem($objectToPutId);
            }
          }
        }
        else if($relationship->isSingle)
        {
          // when the relationship is single (meaning only 1 reference allowed)
          // things get much easier. Namely we just put the model in the related array
          // even if the relationship is polymorphic it doesn't matter.
          $this->relatedViaFieldOnEntity[$relationship->relationshipName] = $objectToPut;
          // and finally we also set the new id on the current entity
          $objectToPutId = $objectToPut->getId();
          if(!empty($objectToPutId))
          {
            $this->entity->$relationshipField->$relationshipColumn = $objectToPutId;
          }
        }
      }
      else if($relationship instanceof ReferencedRelationship)
      {
        if(!array_key_exists($relationship->relationshipName, $this->relatedViaFieldOnExternalEntity))
        {
          $this->relatedViaFieldOnExternalEntity[$relationship->relationshipName] = Collection::forge($relationship->modelType);
        }

        $this->relatedViaFieldOnExternalEntity[$relationship->relationshipName]->put($objectToPut);
      }
    }
  }

  public function debugEntity()
  {
    $values = array();
    $fieldDefinitions = static::getFieldDefinitions();

    foreach ($fieldDefinitions as $fieldName => $fieldDefinition)
    {
      $values[$fieldName] = $this->getFieldValue($fieldName, $fieldDefinition);
    }

    return $values;
  }

  public static function hasRelationship($relationshipName)
  {
    $sourceModelType = get_called_class();
    static::setRelationships($sourceModelType);
    return array_key_exists($sourceModelType, static::$relationships) && array_key_exists($relationshipName, static::$relationships[$sourceModelType]);
  }

  public static function getNextKey()
  {
      return 'PLH'.(static::$keyIndex++);
  }

  public static function createNew()
  {
      if(!empty(static::$bundle))
      {
          $entity = entity_create(static::$entityType, array('type' => static::$bundle));
      }
      else
      {
          $entity = entity_create(static::$entityType);
      }
      return static::forge($entity);
  }

  public static function forge($entity = null, $id = null)
  {
    if(!empty($id))
    {
      $query = static::getModelQuery();

      // add a condition on the id
      $query->addCondition(new Condition(static::$idField, '=', $id));
      $model = $query->fetchSingleModel();

      if(!empty($model))
      {
        return $model;
      }
    }

    if(empty($entity))
    {
      $values = array();
      if(!empty(static::$bundle))
      {
        $values['type'] = static::$bundle;
      }

      $entity = entity_create(static::$entityType, $values);
    }

    if(!empty($entity))
    {
        return new static($entity);
    }
  }

  public static function getModelQuery()
  {
      return new ModelQuery(get_called_class());
  }

  public static function getEntityQuery()
  {
      return new EntityQuery(static::$entityType);
  }

  public static function getBundleQuery()
  {
      return new BundleQuery(static::$entityType, static::$bundle);
  }

  public static function getReferencedRelationshipForFieldRelationship($fieldRelationship)
  {
    $relationships = static::getRelationships();
    $referencedRelationship = null;
    foreach($relationships as $relationship)
    {
      if($relationship instanceof ReferencedRelationship)
      {
        if($relationship->fieldRelationship === $fieldRelationship)
        {
          $referencedRelationship = $relationship;
          break;
        }
      }
    }

    return $referencedRelationship;
  }

  public static function relationships(){}
  public static function setRelationships($modelType)
  {
    if(!array_key_exists($modelType, static::$relationships))
    {
      static::$relationships[$modelType] = array();
      static::relationships();
    }
  }

  public static function getRelationship($relationshipName)
  {
    $sourceModelType = get_called_class();
    static::setRelationships($sourceModelType);

    if($sourceModelType::hasRelationship($relationshipName))
    {
      return static::$relationships[$sourceModelType][$relationshipName];
    }
    else
    {
      throw new RelationshipNotDefinedException('Relationship '.$relationshipName.' does not exist');
    }
  }

  public static function getRelationshipByFieldName($fieldName)
  {
    $relationships = static::getRelationships();
    $foundRelationship = null;

    foreach($relationships as $relationship)
    {
      if($relationship instanceof FieldRelationship && $relationship->getField() === $fieldName)
      {
        $foundRelationship = $relationship;
        break;
      }
      // TODO: make this work with entity reference multi-field
    }

    return $foundRelationship;
  }

  public static function getRelationships()
  {
    $sourceModelType = get_called_class();
    static::setRelationships($sourceModelType);

    return static::$relationships[$sourceModelType];
  }

  public static function addRelationship(Relationship $relationship)
  {
    // first we need to namespace the relationships, as the relationship array is staticly defined;
    // meaning if we would add 2 relationships with the same name on different models, the first one would be overridden
    // we use the relationshipKey, which is a namespaced version with the relationship source added
    $sourceModelType = get_called_class();
    if(!array_key_exists($sourceModelType, static::$relationships))
    {
      static::$relationships[$sourceModelType] = array();
    }

    $relationship->setRelationshipSource($sourceModelType);
    static::$relationships[$sourceModelType][$relationship->getRelationshipKey()] = $relationship;
  }

  public static function getFieldDefinitions()
  {
    if(empty(static::$bundle))
    {
      return \Drupal::service('entity_field.manager')->getFieldDefinitions(static::$entityType, static::$entityType);
    }
    else
    {
      return \Drupal::service('entity_field.manager')->getFieldDefinitions(static::$entityType, static::$bundle);
    }
  }

  public static function getFieldDefinition($fieldName)
  {
    $fieldDefinition = null;
    $fieldDefinitions = static::getFieldDefinitions();
    if(array_key_exists($fieldName, $fieldDefinitions))
    {
      $fieldDefinition = $fieldDefinitions[$fieldName];
    }
    return $fieldDefinition;
  }

  public function __get($property)
  {
		if (property_exists($this, $property))
		{
			return $this->$property;
		}
		else if(array_key_exists($property, $this->relatedViaFieldOnEntity)) // lets check for pseudo properties
		{
			return $this->relatedViaFieldOnEntity[$property];
		}
		else if(array_key_exists($property, $this->relatedViaFieldOnExternalEntity)) // lets check for pseudo properties
		{
			return $this->relatedViaFieldOnExternalEntity[$property];
		}
	}

  public function __isset($property)
  {
    // Needed for twig to be able to access relationship via magic getter
    return property_exists($this, $property) || array_key_exists($property, $this->relatedViaFieldOnEntity) || array_key_exists($property, $this->relatedViaFieldOnExternalEntity);
  }

  public function beforeInsert(){}
  public function afterInsert(){}
  public function beforeUpdate(){}
  public function afterUpdate(){}
  public function beforeDelete(){}

  public function getFieldValue($fieldName, $fieldDefinition = null)
  {
    // lets check if the fieldDefinition was passed, else let's get it
    if($fieldDefinition === null)
    {
      $fieldDefinition = static::getFieldDefinition($fieldName);
    }

    if($fieldDefinition !== null)
    {
      $value;

      // First let's check the manual fields
      if($fieldName === 'type')
      {
        $value = $this->entity->get($fieldName)->target_id;
      }
      else if($fieldName === static::$idField)
      {
        $value = $this->entity->get($fieldName)->value;
      }

      // Now we'll check the other fields
      switch ($fieldDefinition->getType())
      {
        case 'geolocation':
          $value = array();
          $value['lat'] = $this->entity->get($fieldName)->lat;
          $value['lng'] = $this->entity->get($fieldName)->lng;
          break;
        case 'entity_reference':
          $value = array();
          $value['id'] = $this->entity->get($fieldName)->target_id;
          break;
        default:
          $value = $this->entity->get($fieldName)->value;
          break;
      }

      return $value;
    }
  }

  public function serialize()
  {
    $root = new JsonApiRootNode();
    $node = $this->getJsonApiNode();
    $root->addNode($node);

    return $root->serialize();
  }

  // This function returns a mapping of the different fields, with "field_" stripped, and a dasherized representation of the field name
  public static function getPrettyFieldsToFieldsMapping()
  {
    $mapping = array();
    $fieldList = static::getFieldDefinitions();

    foreach($fieldList as $key => $value)
    {
      $fieldnamepretty = StringUtils::dasherize(str_replace('field_', '', $key));
      $mapping[$fieldnamepretty] = $key;
    }

    return $mapping;
  }

  // This function returns the inverse of getPrettyFieldsToFieldsMapping(), for mapping pretty fields back to the original
  public static function getFieldsToPrettyFieldsMapping()
  {
    $prettyMapping = static::getPrettyFieldsToFieldsMapping();

    $mapping = array();
    foreach($prettyMapping as $pretty => $field)
    {
      $mapping[$field] = $pretty;
    }

    return $mapping;
  }

  public static function getFieldForPrettyField($prettyField)
  {
    $field = null;
    $prettyToFieldsMap = static::getPrettyFieldsToFieldsMapping();

    if(array_key_exists($prettyField, $prettyToFieldsMap))
    {
      $field = $prettyToFieldsMap[$prettyField];
    }

    return $field;
  }

  public static function getModelClassForEntityAndBundle($entity, $bundle)
  {
    static::setModelClassMappings();

    $key = Model::getKeyForEntityAndBundle($entity, $bundle);

    if(array_key_exists($key, static::$modelClassMapping))
    {
      return static::$modelClassMapping[$key];
    }
    else
    {
      throw new ModelClassNotDefinedException('no model class for entity '.$entity.' and bundle '.$bundle.' has been defined');
    }
  }

  private static function setModelClassMappings()
  {
    if(static::$modelClassMapping === null)
    {
      static::$modelClassMapping = array();

      if(!function_exists('get_registered_model_classes'))
      {
        throw new NotImplementedException('function get_registered_model_classes() hasn\'t been implemented yet');
      }

      foreach(get_registered_model_classes() as $modelClassName)
      {
        $entity = $modelClassName::$entityType;
        $bundle = $modelClassName::$bundle;

        if(empty($entity))
        {
          throw new InvalidTypeException('Entity Type not defined for '.$modelClassName);
        }

        $key = Model::getKeyForEntityAndBundle($entity, $bundle);

        static::$modelClassMapping[$key] = $modelClassName;
      }
    }
  }

  private static function getKeyForEntityAndBundle($entity, $bundle)
  {
    return empty($bundle) ? $entity : $entity.'.'.$bundle;
  }

  // This method returns the current Model as a JsonApiNode (jsonapi.org)
  public function getJsonApiNode()
  {
    $node = new JsonApiNode();

    $ignore_fields = array('revision_log', 'vid', 'revision_timestamp', 'revision_uid', 'revision_log', 'revision_translation_affected', 'revision_translation_affected', 'default_langcode', 'path', 'content_translation_source', 'content_translation_outdated', 'pass');
    $manual_fields = array($this::$idField, 'type');

    $fieldToPrettyMapping = static::getFieldsToPrettyFieldsMapping();
    $fieldDefinitions = static::getFieldDefinitions();

    foreach($fieldDefinitions as $fieldName => $fieldDefinition)
    {
      // First let's check the manual fields
      if($fieldName === 'type')
      {
        $node->setType($this->entity->get($fieldName)->target_id);
      }
      else if($fieldName === static::$idField)
      {
        $node->setId($this->entity->get($fieldName)->value);
      }

      // Now we'll check the other fields
      if(!in_array($fieldName, $ignore_fields) && !in_array($fieldName, $manual_fields))
      {
        $fieldNamePretty = $fieldToPrettyMapping[$fieldName];
        switch ($fieldDefinition->getType())
        {
          case 'geolocation':
            $attribute = new \stdClass();
            $attribute->lat = $this->entity->get($fieldName)->lat;
            $attribute->lng = $this->entity->get($fieldName)->lng;

            $node->addAttribute($fieldNamePretty, $attribute);
            break;
          case 'entity_reference':
            // this is really hacky, we must consider finding a more performant solution that the one with the target_ids now
            if(!empty($this->entity->get($fieldName)->entity))
            {
              $relationshipDataNode = new JsonApiDataNode();
              $idsThatHaveBeenset = array();
              foreach($this->entity->get($fieldName) as $referencedEntity)
              {
                $target_id = $referencedEntity->target_id;

                if(!array_key_exists($target_id, $idsThatHaveBeenset))
                {
                  $idsThatHaveBeenset[$target_id] = $target_id;
                  $relationshipNode = new JsonApiNode();
                  $relationshipNode->setId($referencedEntity->target_id);
                  $relationshipNode->setType($referencedEntity->entity->bundle());
                  $relationshipDataNode->addNode($relationshipNode);
                }
              }
              $node->addRelationship($fieldNamePretty, $relationshipDataNode);
            }
            break;
          case 'image':
            $attribute = new \stdClass();
            if(!empty($this->entity->get($fieldName)->entity))
            {
              $attribute->width = $this->entity->get($fieldName)->width;
              $attribute->height = $this->entity->get($fieldName)->height;
              $attribute->alt = $this->entity->get($fieldName)->alt;
              $attribute->title = $this->entity->get($fieldName)->title;
              $attribute->url = $this->entity->get($fieldName)->entity->url();

              $attribute->filename = $this->entity->get($fieldName)->entity->get('filename')->value;
              $attribute->uri = $this->entity->get($fieldName)->entity->get('uri')->value;
              $attribute->filemime = $this->entity->get($fieldName)->entity->get('filemime')->value;
              $attribute->filesize = $this->entity->get($fieldName)->entity->get('filesize')->value;

              $node->addAttribute($fieldNamePretty, $attribute);
            }
            else
            {
              $node->addAttribute($fieldNamePretty, null);
            }
            break;
          case 'created':
          case 'changed':
            // for some reason, created and changed aren't regular datetimes, they are unix timestamps in the database
            $timestamp = $this->entity->get($fieldName)->value;
            $datetime = \DateTime::createFromFormat('U', $timestamp);
            $node->addAttribute($fieldNamePretty, $datetime->format( 'c' ));
            break;
          default:
            $node->addAttribute($fieldNamePretty, $this->entity->get($fieldName)->value);
            break;
        }
      }
    }

    // some entity types don't have a type field, we must rely on static definitions
    if(!$node->hasType())
    {
      // some entity types don't have a bundle (user for example) so we must rely on the entity type itself
      if(empty(static::$bundle))
      {
        $node->setType(static::$entityType);
      }
      else
      {
        $node->setType(static::$bundle);
      }
    }

    return $node;
  }
}
