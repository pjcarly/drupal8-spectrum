<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Exceptions\InvalidRelationshipTypeException;
use Drupal\spectrum\Exceptions\RelationshipNotDefinedException;
Use Drupal\spectrum\Utils\String;

use Drupal\spectrum\Serializer\ModelSerializer;

abstract class Model
{
  public static $entityType;
  public static $bundle;
  public static $idField;

  public static $relationships = array();
  public static $relationshipsSet = false;
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

  public function validate()
  {
    return new Validation($this);
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
            $childModel->put($relationship->parentRelationshipName, $this);
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

          $relationshipModelType = $relationship->modelType;
          $relationshipModelQuery = $relationshipModelType::getModelQuery();
          $relationshipCondition = $relationship->getCondition();

          if($relationship instanceof ParentRelationship)
          {
              $parentId = $this->getParentId($relationship);

              if(!empty($parentId))
              {
                  // we set the parent ids in the condition, and fetch the collection of parents
                  $relationshipCondition->value = $parentId;
                  $relationshipCondition->operator = '=';
                  $relationshipModelQuery->addCondition($relationshipCondition);

                  $parentModel = $relationshipModelQuery->fetchSingleModel();

                  if(!empty($parentModel))
                  {
                      $this->put($relationship, $parentModel);

                      // now we musnt forget to put the model as child on the parent for circular references
                      $childRelationship = $relationshipModelType::getChildRelationshipForParentRelationship($relationship);
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
                  $relationshipModelQuery->addCondition($relationshipCondition);

                  $childCollection = $relationshipModelQuery->fetchCollection();

                  foreach($childCollection->models as $childModel)
                  {
                      $this->put($relationship, $childModel);
                      $childModel->put($relationship->parentRelationship, $this);
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

          if($relationship instanceof ParentRelationship)
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
      if($relationship instanceof ParentRelationship)
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

      if($relationship instanceof ParentRelationship)
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
      return array_key_exists($relationshipName, static::$relationships);
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

  public static function getQuery()
  {
      return new Query(static::$entityType, static::$bundle);
  }

  public static function getChildRelationshipForParentRelationship($parentRelationship)
  {
      $relationships = static::getRelationships();
      $childRelationship = null;
      foreach($relationships as $relationship)
      {
          if($relationship instanceof ChildRelationship)
          {
              if($relationship->parentRelationship === $parentRelationship)
              {
                  $childRelationship = $relationship;
                  break;
              }
          }
      }

      return $childRelationship;
  }

  public static function setRelationships(){}

  public static function getRelationship($relationshipName)
  {
      if(!static::$relationshipsSet)
      {
          static::setRelationships();
          static::$relationshipsSet = true;
      }

      if(static::hasRelationship($relationshipName))
      {
          return static::$relationships[$relationshipName];
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
      if($relationship instanceof ParentRelationship && $relationship->getField() === $fieldName)
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
      if(!static::$relationshipsSet)
      {
          static::setRelationships();
          static::$relationshipsSet = true;
      }

      return static::$relationships;
  }

  public static function addRelationship(Relationship $relationship)
  {
      static::$relationships[$relationship->relationshipName] = $relationship;
  }

  public static function getFieldDefinitions()
  {
    return \Drupal::service('entity_field.manager')->getFieldDefinitions(static::$entityType, static::$bundle);
  }

  public static function getFieldDefinition($fieldName)
  {
    $fieldDefinition;
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

      switch ($fieldDefinition->getType()) {
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

  public function toJsonApi()
  {
    $serializer = new ModelSerializer($this);
    return $serializer->serialize('json-api');
  }
}
