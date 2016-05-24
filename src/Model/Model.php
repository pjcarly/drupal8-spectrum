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
Use Drupal\spectrum\Utils\String;

use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiNode;
use Drupal\spectrum\Serializer\JsonApiDataNode;

abstract class Model
{
  public static $entityType;
  public static $bundle;
  public static $idField;

  public static $modelClassMapping = null;
  public static $relationships = null;
  public static $keyIndex = 1;

  public $entity;
  public $key;

  public $parents = array();
  public $children = array();

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
        $this->setParentIdForChildren();
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

  private function setParentIdForChildren()
  {
    $relationships = static::getRelationships();
    foreach($relationships as $relationship)
    {
      if($relationship instanceof ChildRelationship)
      {
        $childRelationship = $this->get($relationship->relationshipName);
        if(!empty($childRelationship))
        {
          foreach($childRelationship->models as $childModel)
          {
            $childModel->put($relationship->fieldRelationshipName, $this);
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
              $parentId = $this->getParentId($relationship);

              if(!empty($parentId))
              {
                  // we set the parent ids in the condition, and fetch the collection of parents
                  $relationshipCondition->value = $parentId;
                  $relationshipCondition->operator = '=';
                  $relationshipQuery->addCondition($relationshipCondition);

                  $parentModel = $relationshipQuery->fetchSingleModel();

                  if(!empty($parentModel))
                  {
                      $this->put($relationship, $parentModel);

                      // now we musn't forget to put the model as child on the parent for circular references
                      $relationshipModelType = $relationship->modelType;
                      $childRelationship = $relationshipModelType::getChildRelationshipForFieldRelationship($relationship);
                      if(!empty($childRelationship))
                      {
                         $parentModel->put($childRelationship, $this);
                      }
                  }
              }
          }
          else if($relationship instanceof ChildRelationship)
          {
              $id = $this->getId();
              if(!empty($id))
              {
                  $relationshipCondition->value = array($id);
                  $relationshipQuery->addCondition($relationshipCondition);

                  $childCollection = $relationshipQuery->fetchCollection();

                  foreach($childCollection->models as $childModel)
                  {
                      $this->put($relationship, $childModel);
                      $childModel->put($relationship->fieldRelationship, $this);
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

  public function get($relationshipName)
  {
      $firstRelationshipNameIndex = strpos($relationshipName, '.');

      if(empty($firstRelationshipNameIndex))
      {
          $relationship = static::getRelationship($relationshipName);

          if($relationship instanceof FieldRelationship)
          {
              return $this->getParent($relationship);
          }
          else if($relationship instanceof ChildRelationship)
          {
              if(array_key_exists($relationship->relationshipName, $this->children))
              {
                  return $this->children[$relationship->relationshipName];
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

      if(!empty($this->entity->$idField->value))
      {
          return $this->entity->$idField->value;
      }
  }

  public function getParentId($relationship)
  {
      if($relationship instanceof FieldRelationship)
      {
          $entity = $this->entity;
          $field = $relationship->getField();
          $column = $relationship->getColumn();

          return empty($entity->$field->$column) ? null : $entity->$field->$column;
      }
      else
      {
          throw new InvalidRelationshipTypeException('Only Parent relationships allowed');
      }
  }

  public function isParentOf($model, $relationship)
  {
      $parentId = $model->getParentId($relationship);
      $id = $this->getId();
      return !empty($parentId) && !empty($id) && $parentId == $id;
  }

  public function getParent($relationship)
  {
      $parents = $this->parents;
      if(array_key_exists($relationship->relationshipName, $parents))
      {
          return $parents[$relationship->relationshipName];
      }
      else
      {
          return null;
      }
  }

  public function put($relationship, $model)
  {
    if($model != null && ($model instanceof Model))
    {
      if(is_string($relationship)) // we only have the relationship name
      {
        $relationship = static::getRelationship($relationship);
      }

      if($relationship instanceof FieldRelationship)
      {
        $this->parents[$relationship->relationshipName] = $model;
        $relationshipField = $relationship->getField();
        $relationshipColumn = $relationship->getColumn();

        $this->entity->$relationshipField->$relationshipColumn = $model->getId();
      }
      else if($relationship instanceof ChildRelationship)
      {
        if(!array_key_exists($relationship->relationshipName, $this->children))
        {
          $this->children[$relationship->relationshipName] = Collection::forge($relationship->modelType);
        }

        $this->children[$relationship->relationshipName]->put($model);
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
    static::setRelationships();

    $relationshipNamespace = get_called_class();
    return array_key_exists($relationshipNamespace . '.' . $relationshipName, static::$relationships);
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

  public static function getChildRelationshipForFieldRelationship($fieldRelationship)
  {
      $relationships = static::getRelationships();
      $childRelationship = null;
      foreach($relationships as $relationship)
      {
          if($relationship instanceof ChildRelationship)
          {
              if($relationship->fieldRelationship === $fieldRelationship)
              {
                  $childRelationship = $relationship;
                  break;
              }
          }
      }

      return $childRelationship;
  }

  public static function relationships(){}
  public static function setRelationships()
  {
    if(static::$relationships === null)
    {
      static::$relationships = array();
      static::relationships();
    }
  }

  public static function getRelationship($relationshipName)
  {
    static::setRelationships();

    if(static::hasRelationship($relationshipName))
    {
      $relationshipNamespace = get_called_class();
      return static::$relationships[$relationshipNamespace . '.' . $relationshipName];
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
      static::setRelationships();

      return static::$relationships;
  }

  public static function addRelationship(Relationship $relationship)
  {
    // first we need to namespace the relationships, as the relationship array is staticly defined;
    // meaning if we would add 2 relationships with the same name on different models, the first one would be overridden
    // we use the relationshipKey, which is a namespaced version with the relationship source added
    $relationship->setRelationshipSource(get_called_class());
    static::$relationships[$relationship->getRelationshipKey()] = $relationship;
  }

  public static function getFieldDefinitions()
  {
    return \Drupal::service('entity_field.manager')->getFieldDefinitions(static::$entityType, static::$bundle);
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
		else if(array_key_exists($property, $this->parents)) // lets check for pseudo properties
		{
			return $this->parents[$property];
		}
		else if(array_key_exists($property, $this->children)) // lets check for pseudo properties
		{
			return $this->children[$property];
		}
	}

  public function __isset($property)
  {
    // Needed for twig to be able to access relationship via magic getter
    return property_exists($this, $property) || array_key_exists($property, $this->parents) || array_key_exists($property, $this->children);
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
      $fieldnamepretty = String::dasherize(str_replace('field_', '', $key));
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
    if(array_key_exists($entity.'.'.$bundle, static::$modelClassMapping))
    {
      return static::$modelClassMapping[$entity.'.'.$bundle];
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

        if(empty($entity) || empty($bundle))
        {
          throw new InvalidTypeException('Entity Type or Bundle not defined for '.$modelClassName);
        }

        static::$modelClassMapping[$entity.'.'.$bundle] = $modelClassName;
      }
    }
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
            if(!empty($this->entity->get($fieldName)->entity))
            {
              $relationshipDataNode = new JsonApiDataNode();
              foreach($this->entity->get($fieldName) as $referencedEntity)
              {
                $relationshipNode = new JsonApiNode();
                $relationshipNode->setId($referencedEntity->target_id);
                $relationshipNode->setType($referencedEntity->entity->bundle());
                $relationshipDataNode->addNode($relationshipNode);
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
            $datetime = \DateTime::createFromFormat( 'U', $timestamp );
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
