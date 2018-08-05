<?php

namespace Drupal\spectrum\Model;

use Drupal\Core\Entity\EntityInterface;
use Drupal\spectrum\Query\EntityQuery;
use Drupal\spectrum\Query\BundleQuery;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

use Drupal\spectrum\Exceptions\InvalidTypeException;
use Drupal\spectrum\Exceptions\NotImplementedException;
use Drupal\spectrum\Exceptions\ModelClassNotDefinedException;
use Drupal\spectrum\Exceptions\InvalidRelationshipTypeException;
use Drupal\spectrum\Exceptions\RelationshipNotDefinedException;
use Drupal\spectrum\Exceptions\PolymorphicException;
use Drupal\spectrum\Exceptions\InvalidFieldException;

use Drupal\spectrum\Utils\StringUtils;
use Drupal\spectrum\Permissions\PermissionServiceInterface;
use Drupal\spectrum\Models\User;

/**
 * A Model is a wrapper around a Drupal Entity, which provides extra functionality. and an easy way of fetching and saving it to the database.
 * It also provides functionality to correctly fetch related records, defined by Relationships (which are linked through an EntityReference)
 * and correctly insert, update, delete and validate
 * entities by respecting the UnitOfWork design pattern.
 *
 * Together with Collection and Relationship, this is the Core of the Spectrum framework
 * This functionality is loosly based on BookshelfJS (http://bookshelfjs.org/)
 */
abstract class Model
{
  use \Drupal\spectrum\Serializer\ModelSerializerMixin;
  use \Drupal\spectrum\Serializer\ModelDeserializerMixin;
  use \Drupal\spectrum\Serializer\ModelSQLHelperMixin;

  /**
   * The entity type of this model (for example "node"), this should be defined in every subclass
   *
   * @var string
   */
  public static $entityType;

  /**
   * The bundle of this model (for example "article"), this should be defined in every subclass
   *
   * @var string
   */
  public static $bundle;

  /**
   * The name of the id field of the entity (for example "nid"), this should be defined in eery subclass
   *
   * @var string
   */
  public static $idField;

  /**
   * Here are the model class mapping stored, with the entitytype/bundle as key, and the fully qualified model classname as value
   * This is to get around the shared scope of multiple Models on the abstract superclass Model
   *
   * @var array
   */
  public static $modelClassMapping = null;

  /**
   * This array will hold the defined relationships with as key the fully qualified classname of the model, and as value the different defined relationships
   *
   * @var array
   */
  public static $relationships = [];

  /**
   * This is a incrementing helper variable to assign temporary keys to models that havent been inserted yet
   *
   * @var integer
   */
  public static $keyIndex = 1;

  /**
   * Here the serialization type aliases will be stored in a shared scope safe way
   *
   * @var array
   */
  protected static $serializationTypeAliases = [];

  /**
   * The entity that was wrapped by this Model
   *
   * @var EntityInterface
   */
  public $entity;

  /**
   * The unique key for this model used by Collections, normally this is the Entity ID, in case no id exists (new records) use a temp key
   *
   * @var string|int|null
   */
  public $key;

  /**
   * This var has no functional use. however, in practice this is often used in different scenarios to select a model in a list for example
   *
   * @var boolean
   */
  public $selected = false;

  /**
   * An array containing all the FieldRelationships to this Entity, with as Key the relationship name
   * The value will most likely be a Model, but can also be a Collection in case of a entity reference field with multiple values (fieldCardinality > 0)
   *
   * @var array
   */
  public $relatedViaFieldOnEntity = [];

  /**
   * An array containing all the ReferencedRelationships to this Entity, with as Key the relationship name
   * The value will always be a collection
   *
   * @var array
   */
  public $relatedViaFieldOnExternalEntity = [];

  /**
   * In the constructor for the Model, an drupal entity must be provided
   *
   * @param EntityInterface $entity
   */
  public function __construct(EntityInterface $entity)
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

  /**
   * Returns a string that can be used in a BaseApiController to switch on and create a ModelApiHandler
   * This should be overridden in every ModelClass you want to define a Generic ModelApiHandler for with the key you want the BaseApiController to discover the Model with
   *
   * @return string
   */
  public static function getGenericApiHandlerKey() : string
  {
    return '';
  }

  /**
   * Check whether the Model is new (not yet persisted to the DB)
   *
   * @return boolean
   */
  public function isNew() : bool
  {
    return empty($this->getId());
  }

  /**
   * Save the model, or if a relationshipName was passed, get the relationship and save it
   *
   * @param string $relationshipName
   * @return Model
   */
  public function save(string $relationshipName = NULL) : Model
  {
    if(empty($relationshipName))
    {
      $isNew = $this->isNew();
      $this->entity->save();

      if($isNew)
      {
        $this->setFieldForReferencedRelationships();
        $this->updateKeys();
      }
    }
    else
    {
      $this->get($relationshipName)->save();
    }

    return $this;
  }

  /**
   * Delete the Model from the database. This should only be used when this model doesnt exists in a Collection (whether it is a relationship or not)
   * Else delete it via the Collection, so the UnitOfWork can do its job
   *
   * @return Model
   */
  public function delete() : Model
  {
    if(!$this->isNew())
    {
      $this->entity->delete();
    }

    return $this;
  }

  /**
   * Update the Key of this Model with the ID, and update the key in every Relationship (and inverse) where this Model was already put
   * This is used when a temporary Key is generated, the Model is saved, and the Key is updated to the ID of the model
   *
   * @return Model
   */
  private function updateKeys() : Model
  {
    // we start of by reputting our keys
    $oldKey = $this->key;
    $this->key = $this->getId();
    // This method updates the current key, and all the inverse keys as well
    $relationships = static::getRelationships();
    foreach($relationships as $relationship)
    {
      if($relationship instanceof FieldRelationship)
      {
        // first we check if it is a single or multipel value field relationship
        if($relationship->isMultiple)
        {
          // If we have a relationship where multiple values can be in a field
          // we must get every value, loop over it, and update the referencing collection on each value
          $referencedModels = $this->get($relationship);

          foreach($referencedModels as $referencedModel)
          {
            $referencedEntityType = $referencedModel->entity->getEntityTypeId();
            $referencedEntityBundle = empty($referencedModel->entity->type) ? null : $referencedModel->entity->type->target_id;
            $referencedModelType = Model::getModelClassForEntityAndBundle($referencedEntityType, $referencedEntityBundle);

            // we must also check for an inverse relationship and, if found, put the inverse as well
            $referencedRelationship = $referencedModelType::getReferencedRelationshipForFieldRelationship($relationship);

            $referencingCollectionOnReferencedModel = $referencedModel->get($referencedRelationship);
            $referencingCollectionOnReferencedModel->replaceOldModelKey($oldKey, $this->key);
          }
        }
        else
        {
          // It is a single value, so we just need to get the inverse referencing collection, and update the key
          $referencedModel = $this->get($relationship);

          if(!empty($referencedModel))
          {
            $referencedEntityType = $referencedModel->entity->getEntityTypeId();
            $referencedEntityBundle = empty($referencedModel->entity->type) ? null : $referencedModel->entity->type->target_id;
            $referencedModelType = Model::getModelClassForEntityAndBundle($referencedEntityType, $referencedEntityBundle);

            // we must also check for an inverse relationship and, if found, put the inverse as well
            $referencedRelationship = $referencedModelType::getReferencedRelationshipForFieldRelationship($relationship);

            if(!empty($referencedRelationship)) // referencedRelationship is optional
            {
              $referencingCollectionOnReferencedModel = $referencedModel->get($referencedRelationship);
              $referencingCollectionOnReferencedModel->replaceOldModelKey($oldKey, $this->key);
            }
          }
        }
      }
      else if($relationship instanceof ReferencedRelationship)
      {
        // This is a little complicated, we only need to update the keys when:
        // - The field relationship of the inverse contains multiple values
        // In that case, the value on the inverse is a collection, and this model will be stored there with its key
        // In all other cases, a single model is stored in the relationship arrays, where no key is used

        $inverseRelationship = $relationship->fieldRelationship;
        if($inverseRelationship->isMultiple)
        {
          // we get the collection keeping all the models that refer to this model via a field
          $referencingModels = $this->get($relationship);
          foreach($referencingModels as $referencingModel)
          {
            // Now we get the collection on our referencing model, where this model should be an item in
            $inverseReferencedCollection = $referencingModel->get($inverseRelationship);
            $inverseReferencedCollection->replaceOldModelKey($oldKey, $this->key);
          }
        }
      }
    }

    return $this;
  }

  /**
   * Clear the relationship from this Model
   *
   * @param string $relationshipName
   * @return void
   */
  public function clear(string $relationshipName)
  {
    $relationship = static::getRelationship($relationshipName);
    if($relationship instanceof FieldRelationship)
    {
      unset($this->relatedViaFieldOnEntity[$relationshipName]);
      $field = $relationship->getField();
      $column = $relationship->getColumn();
      $this->entity->{$field}->{$column} = null;
    }
    else if($relationship instanceof ReferencedRelationship)
    {
      unset($this->relatedViaFieldOnExternalEntity[$relationshipName]);
    }
  }

  /**
   * Validate the Model, or if a relationshipName was provided, the relationship
   *
   * @param string $relationshipName
   * @return Validation
   */
  public function validate(string $relationshipName = NULL) : Validation
  {
    if(empty($relationshipName))
    {
      $validation = new Validation($this);
      // next we do a workaround for entity reference fields, referencing entities that are deleted
      $validation->processInvalidReferenceConstraints();
      return $validation;
    }
    else
    {
      return $this->get($relationshipName)->validate();
    }
  }

  /**
   * This is the UnitOfWork Design Pattern in practice
   * This method sets the field with the newly created ID upon inserting a new record
   * This is important for when you have multiple related models in memory who haven't been inserted
   * and are just related in memory, by setting the ID we know how to relate them in the DB
   *
   * @return Model
   */
  private function setFieldForReferencedRelationships() : Model
  {
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

    return $this;
  }

  /**
   * Fetch the relationship from the Database
   *
   * @param string $relationshipName
   * @return Model|Collection|null
   */
  public function fetch(string $relationshipName)
  {
    $returnValue = null;

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
              foreach($referencedEntities as $referencedEntity)
              {
                $referencedModel = null;
                if($relationship->isPolymorphic || empty($referencedModelType))
                {
                  // if the relationship is polymorphic we can get multiple bundles, so we must define the modeltype based on the bundle and entity of the current looping entity
                  // or if the related modeltype isn't set yet, we must set it once
                  $referencedEntityType = $referencedEntity->getEntityTypeId();
                  $referencedEntityBundle = empty($referencedEntity->type) ? null : $referencedEntity->type->target_id;
                  $referencedModelType = Model::getModelClassForEntityAndBundle($referencedEntityType, $referencedEntityBundle);
                }

                // now that we have a model, lets put them one by one
                $referencedModel = $referencedModelType::forge($referencedEntity);
                $returnValue = $this->put($relationship, $referencedModel, true);
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
              $referencedEntityBundle = empty($referencedEntity->type) ? null : $referencedEntity->type->target_id;
              $referencedModelType = Model::getModelClassForEntityAndBundle($referencedEntityType, $referencedEntityBundle);

              // now that we have a model, lets put them one by one
              $referencedModel = $referencedModelType::forge($referencedEntity);
              $returnValue = $this->put($relationship, $referencedModel, true);
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
                $referencingEntityBundle = empty($referencingEntity->type) ? null : $referencingEntity->type->target_id;
                $referencingModelType = Model::getModelClassForEntityAndBundle($referencingEntityType, $referencingEntityBundle);
              }

              // now that we have a model, lets put them one by one
              $referencingModel = $referencingModelType::forge($referencingEntity);
              $returnValue = $this->put($relationship, $referencingModel, true);
              $referencingModel->put($relationship->fieldRelationship, $this, true);
            }
          }
        }
      }
    }
    else
    {
      $secondToLastRelationshipName = substr($relationshipName, 0, $lastRelationshipNameIndex);
      $resultCollection = $this->get($secondToLastRelationshipName);
      if(!empty($resultCollection))
      {
        $lastRelationshipName = substr($relationshipName, $lastRelationshipNameIndex+1);
        $returnValue = $resultCollection->fetch($lastRelationshipName);
      }
    }

    return $returnValue;
  }

  /**
   * Returns the ModelClass name
   *
   * @return string
   */
  public function getModelName() : string
  {
    return get_class($this);
  }

  /**
   * Returns the provided relationship on this Model
   *
   * @param string|Relationship $relationship
   * @return Model|Collection|null
   */
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
        if($relationship->isMultiple)
        {
          if(!array_key_exists($relationship->relationshipName, $this->relatedViaFieldOnEntity))
          {
            $this->createNewCollection($relationship);
          }

          return $this->relatedViaFieldOnEntity[$relationship->relationshipName];
        }
        else
        {
          if(array_key_exists($relationship->relationshipName, $this->relatedViaFieldOnEntity))
          {
            return $this->relatedViaFieldOnEntity[$relationship->relationshipName];
          }
          else
          {
            return NULL;
          }
        }
      }
      else if($relationship instanceof ReferencedRelationship)
      {
        if(!array_key_exists($relationship->relationshipName, $this->relatedViaFieldOnExternalEntity))
        {
          $this->createNewCollection($relationship);
        }

        return $this->relatedViaFieldOnExternalEntity[$relationship->relationshipName];
      }
    }
    else
    {
      $firstRelationshipName = substr($relationship, 0,  $firstRelationshipNameIndex);
      $firstRelationshipGet = $this->get($firstRelationshipName);

      if(!empty($firstRelationshipGet))
      {
        $newRelationshipName = substr($relationship, $firstRelationshipNameIndex+1);
        return $firstRelationshipGet->get($newRelationshipName);
      }
    }

    return null;
  }

  /**
   * Returns the Id of the Model, this correctly handles the different id-fieldnames
   *
   * @return void
   */
  public function getId()
  {
    $idField = static::$idField;
    return $this->entity->$idField->value;
  }

  /**
   * Set the ID of the Model
   *
   * @param int|string|null $id
   * @return void
   */
  public function setId($id)
  {
    $idField = static::$idField;
    $this->entity->$idField->value = $id;
  }

  /**
   * Returns the id of the provided Relationship
   *
   * @param FieldRelationship $relationship
   * @return int|string|null
   */
  public function getFieldId(FieldRelationship $relationship)
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

  /**
   * Create a Collection for a Relationship. Mind you this can be both Field and ReferencedRelationships, ReferencedRelationships will always be a Collection
   * FieldRelationships will only be a Collection if Multiple values can be filled in (fieldCardinality > 1)
   *
   * @param Relationship $relationship
   * @return Collection
   */
  private function createNewCollection(Relationship $relationship) : Collection
  {
    if($relationship instanceof FieldRelationship)
    {
      if($relationship->isMultiple)
      {
        if($relationship->isPolymorphic)
        {
          $this->relatedViaFieldOnEntity[$relationship->relationshipName] = PolymorphicCollection::forge();
        }
        else
        {
          $this->relatedViaFieldOnEntity[$relationship->relationshipName] = Collection::forge($relationship->modelType);
        }

        return $this->relatedViaFieldOnEntity[$relationship->relationshipName];
      }
      else
      {
        throw new InvalidTypeException('Single Field Relationships do not require a collection');
      }
    }
    else if($relationship instanceof ReferencedRelationship)
    {
      $this->relatedViaFieldOnExternalEntity[$relationship->relationshipName] = Collection::forge($relationship->modelType);
      return $this->relatedViaFieldOnExternalEntity[$relationship->relationshipName];
    }
  }

  /**
   * Set the Inverse values of this Model on the provided Relationship
   *
   * @param Relationship $relationship
   * @param Model $referencedModel
   * @return Model
   */
  private function putInverse(Relationship $relationship, Model $referencedModel) : Model
  {
    // if the relationship is polymorphic we can get multiple bundles, so we must define the modeltype based on the bundle and entity of the current looping entity
    // or if the related modeltype isn't set yet, we must set it once
    $referencedEntityType = $referencedModel->entity->getEntityTypeId();
    $referencedEntityBundle = empty($referencedModel->entity->type) ? null : $referencedModel->entity->type->target_id;
    $referencedModelType = Model::getModelClassForEntityAndBundle($referencedEntityType, $referencedEntityBundle);

    // we must also check for an inverse relationship and, if found, put the inverse as well
    $referencedRelationship = $referencedModelType::getReferencedRelationshipForFieldRelationship($relationship);

    // And finally if we found an inverse relationship, lets put (this) on the inverse (defining an inverse is optional, so we can just as easily find no inverses)
    if(!empty($referencedRelationship))
    {
      $referencedModel->put($referencedRelationship, $this, true);
    }

    return $this;
  }

  /**
   * Put the provided object on the provided relationship
   *
   * @param Relationship|string $relationship
   * @param Model|Collection $objectToPut
   * @param boolean $includeInOriginalModels
   * @return void
   */
  public function put($relationship, $objectToPut, $includeInOriginalModels = false)
  {
    $returnValue = null; // we return the object where this object is being put ON (can be a Model, or a Collection)

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
              $returnValue = $this->put($relationshp, $model, $includeInOriginalModels);
            }
          }
          else if($objectToPut instanceof Model)
          {
            // In case we have a model, it means we have to add it to the collection, that potentially doesn't exist yet
            // lets watch out, that the relationship can be polymorphic to create the correct collection if needed
            if(!array_key_exists($relationship->relationshipName, $this->relatedViaFieldOnEntity))
            {
              $this->createNewCollection($relationship);
            }

            // we put the model on the collection
            $collection = $this->relatedViaFieldOnEntity[$relationship->relationshipName];
            $collection->put($objectToPut, $includeInOriginalModels);
            $returnValue = $collection;

            // and also append the entity field with the value (append because there can be multiple items)
            $objectToPutId = $objectToPut->getId();
            if(!empty($objectToPutId))
            {
              $this->entity->$relationshipField->appendItem($objectToPutId);
            }

            // and finally set a possible inverse as well
            $this->putInverse($relationship, $objectToPut);
          }
        }
        else if($relationship->isSingle)
        {
          // when the relationship is single (meaning only 1 reference allowed)
          // things get much easier. Namely we just put the model in the related array
          // even if the relationship is polymorphic it doesn't matter.
          $this->relatedViaFieldOnEntity[$relationship->relationshipName] = $objectToPut;
          $returnValue = $objectToPut;
          // we also set the new id on the current entity
          $objectToPutId = $objectToPut->getId();
          if(!empty($objectToPutId))
          {
            $this->entity->$relationshipField->$relationshipColumn = $objectToPutId;
          }

          // and finally set a possible inverse as well
          $this->putInverse($relationship, $objectToPut);
        }
      }
      else if($relationship instanceof ReferencedRelationship)
      {
        if(!array_key_exists($relationship->relationshipName, $this->relatedViaFieldOnExternalEntity))
        {
          $this->createNewCollection($relationship);
        }

        $collection = $this->relatedViaFieldOnExternalEntity[$relationship->relationshipName];
        $collection->put($objectToPut, $includeInOriginalModels);
        $returnValue = $collection;
      }
    }
    else if($objectToPut === null)
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
          $this->entity->$relationshipField = [];
        }
        else if($relationship->isSingle)
        {
          $this->entity->$relationshipField->$relationshipColumn = null;
        }

        $returnValue = $objectToPut;
      }
    }

    return $returnValue;
  }

  /**
   * Put a new Model on the provided Relationship
   *
   * @param string|FieldRelationship|ReferencedRelationship $relationship
   * @return Model
   */
  public function putNew($relationship) : Model
  {
    if(is_string($relationship)) // we only have the relationship name
    {
      $relationship = static::getRelationship($relationship);
    }

    if($relationship instanceof FieldRelationship)
    {
      if($relationship->isPolymorphic)
      {
        throw new PolymorphicException('PutNew has no meaning for a polymorphic relationship, we can\'t know the type');
      }

      $relationshipModelType = $relationship->modelType;
      $relationshipModel = $relationshipModelType::createNew();
      $this->put($relationship, $relationshipModel);
      return $relationshipModel;
    }
    else if($relationship instanceof ReferencedRelationship)
    {
      $id = $this->getId();
      $relationshipModelType = $relationship->modelType;
      $relationshipModel = $relationshipModelType::createNew();

      // If this record already has an Id, we can fill it in on the new model
      if(!empty($id))
      {
        $relationshipField = $relationship->fieldRelationship->getField();
        $relationshipColumn = $relationship->fieldRelationship->getColumn();

        $relationshipModel->entity->$relationshipField->$relationshipColumn = $id;
      }

      // We put it on the inverse, that way the ->putInverse() is triggered and put on $this as well
      $relationshipModel->put($relationship->fieldRelationship, $this);

      return $relationshipModel;
    }
  }

  /**
   * This method is used to add FieldConstraints at runtime to the Model
   *
   * @return void
   */
  public function constraints(){}

  /**
   * Add a FieldConstraint to the Drupal Entity at Runtime
   *
   * @param string $fieldName
   * @param string $constraintName
   * @param array $options
   * @return Model
   */
  public function addFieldConstraint(string $fieldName, string $constraintName, array $options = []) : Model
  {
    $this->entity->getFieldDefinition($fieldName)->addConstraint($constraintName, $options);
    return $this;
  }

  /**
   * Create a Copy of this entity and wrap it in a Model, the IDs will be blank, and all the field values will be filled in.
   *
   * @return Model
   */
  public function getCopiedModel() : Model
  {
    $copy = static::forgeByEntity($this->getCopiedEntity());
    return $copy;
  }

  /**
   * Create a Copy of this entity, the IDs will be blank, and all the field values will be filled in.
   *
   * @return Entity
   */
  public function getCopiedEntity()
  {
    $entity = $this->entity;
    $copy = $entity->createDuplicate();

    return $copy;
  }

  /**
   * Create a Clone of the Entity, and wrap it in a Model, with all the fields including the IDs filled in.
   *
   * @return Model
   */
  public function getClonedModel() : Model
  {
    $clone = static::forgeByEntity($this->getClonedEntity());
    return $clone;
  }

  /**
   * Create a Clone of this Entity, with all the fields including the IDs filled in.
   *
   * @return Entity
   */
  public function getClonedEntity()
  {
    $entity = $this->entity;
    $clone = $entity->createDuplicate();

    $idField = static::$idField;
    $clone->$idField->value = $this->getId();

    return $clone;
  }

  /**
   * Helper function for trigger methods, this way we can check if the Model being inserted is new or not (we cant use the ID as this will be filled in)
   *
   * @return boolean
   */
  protected function isNewlyInserted() : bool
  {
    return empty($this->entity->original);
  }

  /**
   * Helper function for trigger methods. Returns true if the value of the field changed compared to the value stored in the database
   * This can be used to only execute certain code when a field changes. (For Example when setting the Title of a User based on the first and lastname, only execute the method when the first of the lastname changes)
   *
   * @param string $fieldName
   * @return boolean
   */
  protected function fieldChanged(string $fieldName) : bool
  {
    $returnValue = $this->isNewlyInserted();

    if($returnValue)
    {
      return true;
    }

    $fieldDefinition = static::getFieldDefinition($fieldName);
    $newAttribute = $this->entity->$fieldName;
    $oldAttribute = $this->entity->original->$fieldName;

    switch($fieldDefinition->getType())
    {
      case 'address':
        $returnValue = ($newAttribute->country_code != $oldAttribute->country_code)
                        || ($newAttribute->country_code != $oldAttribute->country_code)
                        || ($newAttribute->administrative_area != $oldAttribute->administrative_area)
                        || ($newAttribute->locality != $oldAttribute->locality)
                        || ($newAttribute->postal_code != $oldAttribute->postal_code)
                        || ($newAttribute->sorting_code != $oldAttribute->sorting_code)
                        || ($newAttribute->address_line1 != $oldAttribute->address_line1)
                        || ($newAttribute->address_line2 != $oldAttribute->address_line2);
        break;
      case 'entity_reference':
      case 'file':
      case 'image':
        $returnValue = ($newAttribute->target_id != $oldAttribute->target_id);
        break;
      case 'geolocation':
        $returnValue = ($newAttribute->lag != $oldAttribute->lag) || ($newAttribute->lng != $oldAttribute->lng);
        break;
      case 'link':
        $returnValue = ($newAttribute->uri != $oldAttribute->uri);
        break;
      default:
        $returnValue = ($newAttribute->value != $oldAttribute->value);
        break;
    }

    return $returnValue;
  }

  /**
   * Checks if the Relationshipname exists
   *
   * @param string $relationshipName
   * @return boolean
   */
  public static function hasRelationship(string $relationshipName) : bool
  {
    $sourceModelType = get_called_class();
    static::setRelationships($sourceModelType);
    return array_key_exists($sourceModelType, static::$relationships) && array_key_exists($relationshipName, static::$relationships[$sourceModelType]);
  }

  /**
   * Identical to hasRelationship, but with the difference that we search for deep relationships via the '.'
   *
   * @param string $relationshipName
   * @return boolean
   */
  public static function hasDeepRelationship(string $relationshipName) : bool
  {
    $sourceModelType = get_called_class();
    $firstRelationshipNamePosition = strpos($relationshipName, '.');

    if(empty($firstRelationshipNamePosition)) // relationship name without extra relationships
    {
      return static::hasRelationship($relationshipName);
    }
    else
    {

      $firstRelationshipName = substr($relationshipName, 0, $firstRelationshipNamePosition);
      if(static::hasRelationship($firstRelationshipName))
      {
        $firstRelationship = static::getRelationship($firstRelationshipName);
        $firstRelationshipModelType = $firstRelationship->modelType;
        return $firstRelationshipModelType::hasDeepRelationship(substr($relationshipName, $firstRelationshipNamePosition+1));
      }
      else
      {
        return false;
      }
    }
  }

  /**
   * Returns a Relationship based on the relationshipName, this will also look for relationships when a "." so can be used to search multiple levels deep
   *
   * @param string $relationshipName
   * @return Relationship
   */
  public static function getDeepRelationship(string $relationshipName) : Relationship
  {
    $sourceModelType = get_called_class();
    $firstRelationshipNamePosition = strpos($relationshipName, '.');

    if(empty($firstRelationshipNamePosition)) // relationship name without extra relationships
    {
      return static::getRelationship($relationshipName);
    }
    else
    {
      $firstRelationshipName = substr($relationshipName, 0, $firstRelationshipNamePosition);
      $nextRelationshipNames  = substr($relationshipName, $firstRelationshipNamePosition+1, strlen($relationshipName));
      $firstRelationship = static::getRelationship($firstRelationshipName);
      $firstRelationshipModelType = $firstRelationship->modelType;

      return $firstRelationshipModelType::getDeepRelationship($nextRelationshipNames);
    }
  }

  /**
   * Generate a new Key in memory, used to generate a temporary key for a model that doesnt exists in the db yet, and the ID cant be used
   *
   * @return string
   */
  public static function getNextKey() : string
  {
      return 'PLH'.(static::$keyIndex++);
  }

  /**
   * Create a new entity and wrap it in this Model
   *
   * @return Model
   */
  public static function createNew() : Model
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

  /**
   * Forge a Model by a Drupal Entity (no queries will be done)
   *
   * @param EntityInterface $entity
   * @return Model|null
   */
  public static function forgeByEntity(EntityInterface $entity) : ?Model
  {
    return static::forge($entity);
  }

  /**
   * Forge a Model by the provided ID, a query will be done to the database
   *
   * @param int|string $id
   * @return Model|null
   */
  public static function forgeById($id) : ?Model
  {
    return static::forge(null, $id);
  }

  /**
   * @deprecated
   * Forge a new Model with either an Drupal Entity or an ID. For ease of use and readability use the methods "forgeById" or "forgeByEntity"
   *
   * @param EntityInterface $entity
   * @param string|int $id
   * @return Model|null will return the Model, or null if the model wasnt found
   */
  public static function forge(EntityInterface $entity = null, $id = null) : ?Model
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

    if(empty($entity) && empty($id))
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

    return null;
  }

  /**
   * This function will clear the static entity cache drupal stores in memory for the remainder of the transaction
   * Any entities that are present in the cache will not be loaded from the database again, instead the php object in the cache will be returned
   * After calling this function, the cache of the entire ENTITY (so all including bundles) will be cleared, and any queries for the entity will
   * be fetched from the database again
   *
   * @return void
   */
  public static function clearDrupalStaticEntityCache() : void
  {
    $entityType = static::$entityType;

    $store = \Drupal::entityManager()->getStorage($entityType);
    $store->resetCache();
  }


  /**
   * This function will clear all the entity caches for every model class in the application (see Model::clearDrupalStaticEntityCache() for more details)
   *
   * @return void
   */
  public static function clearAllDrupalStaticEntityCaches() : void
  {
    $clearedEntitytypes = [];

    foreach(static::getModelClasses() as $modelClass)
    {
      if(!in_array($modelClass::$entityType, $clearedEntitytypes))
      {
        $modelClass::clearDrupalStaticEntityCache();
        $clearedEntitytypes[] = $modelClass::$entityType;
      }
    }
  }

  /**
   * Return a ModelQuery for the current Model (with the correct entity and bundle filled in as Conditions)
   *
   * @return ModelQuery
   */
  public static function getModelQuery() : ModelQuery
  {
      return new ModelQuery(get_called_class());
  }

  /**
   * Return an EntityQuery, (with the correct entity filled in as a Condition)
   *
   * @return EntityQuery
   */
  public static function getEntityQuery() : EntityQuery
  {
      return new EntityQuery(static::$entityType);
  }

  /**
   * Return a BundleQuery with the entityType and bundle filled in as Conditions
   *
   * @return BundleQuery
   */
  public static function getBundleQuery() : BundleQuery
  {
      return new BundleQuery(static::$entityType, static::$bundle);
  }

  /**
   * Return the ReferencedRelationship for a FieldRelationship (the inverse on the other Model), can be null if the ReferencedRelationship isnt defined
   *
   * @param FieldRelationship $fieldRelationship
   * @return ReferencedRelationship|null
   */
  public static function getReferencedRelationshipForFieldRelationship(FieldRelationship $fieldRelationship) : ?ReferencedRelationship
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

  /**
   * This method is used to add relationships on every implementation of a Model
   *
   * @return void
   */
  public static function relationships(){}

  /**
   * This Method sets the relationship arrays (and takes care of the sharing of the scope of Model by multiple Models)
   *
   * @param [type] $modelType
   * @return void
   */
  public static function setRelationships($modelType)
  {
    if(!array_key_exists($modelType, static::$relationships))
    {
      static::$relationships[$modelType] = array();
      static::relationships();
    }
  }

  /**
   * Get a Relationship by Name
   *
   * @param string $relationshipName
   * @return Relationship
   */
  public static function getRelationship(string $relationshipName) : Relationship
  {
    $sourceModelType = get_called_class();
    static::setRelationships($sourceModelType);

    if($sourceModelType::hasRelationship($relationshipName))
    {
      return static::$relationships[$sourceModelType][$relationshipName];
    }
    else
    {
      throw new RelationshipNotDefinedException('Relationship '.$relationshipName.' does not exist on model '.$sourceModelType);
    }
  }

  /**
   * Get a relationship by the fieldname on the drupal entity (used for deserializing)
   *
   * @param string $fieldName
   * @return void
   */
  public static function getRelationshipByFieldName(string $fieldName)
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

  /**
   * Return a jsonapi.org compliant Serialization type (will dasherize types), normally the drupal entity type/bundle will be used, but it is possible to set an alias at runtime
   * See Model::setSerializationTypeAlias()
   *
   * @return string
   */
  public static function getSerializationType() : string
  {
    $returnValue = '';
    $key = static::getKeyForEntityAndBundle(static::$entityType, static::$bundle);
    $alias = array_key_exists($key, static::$serializationTypeAliases) ? static::$serializationTypeAliases[$key] : null;

    if(empty($alias))
    {
      if(empty(static::$bundle))
      {
        $returnValue = static::$entityType;
      }
      else
      {
        $returnValue = static::$bundle;
      }
    }
    else
    {
      $returnValue = $alias;
    }

    return StringUtils::dasherize($returnValue);
  }

  /**
   * This hacky method sets a different serialization type at runtime than the Drupal type. (it is used to give an Entity a different name in a different scenario upon serialization and deserialization)
   *
   * @param string $type
   * @return void
   */
  public static function setSerializationTypeAlias(string $type) : void
  {
    $key = static::getKeyForEntityAndBundle(static::$entityType, static::$bundle);
    static::$serializationTypeAliases[$key] = $type;
  }

  /**
   * Returns an array with all the relationships of the current Model
   *
   * @return array
   */
  public static function getRelationships() : array
  {
    $sourceModelType = get_called_class();
    static::setRelationships($sourceModelType);

    return static::$relationships[$sourceModelType];
  }

  /**
   * Add a relationship to the Model, this function should be used in the relationships() function on every implementation of Model
   *
   * @param Relationship $relationship
   * @return void
   */
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

  /**
   * Returns the drupal field definitions for the entity of this Model
   *
   * @return array
   */
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

  /**
   * Returns the drupal FieldDefinition for the provided fieldName
   *
   * @param string $fieldName
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   */
  public static function getFieldDefinition(string $fieldName) : ?\Drupal\Core\Field\FieldDefinitionInterface
  {
    $fieldDefinition = null;
    $fieldDefinitions = static::getFieldDefinitions();
    if(array_key_exists($fieldName, $fieldDefinitions))
    {
      $fieldDefinition = $fieldDefinitions[$fieldName];
    }
    return $fieldDefinition;
  }

  /**
   * Return the Drupal field settings for a passed in field
   *
   * @param string $fieldName
   * @return array
   */
  public static function getFieldSettings(string $fieldName) : array
  {
    $fieldDefinition = static::getFieldDefinition($fieldName);

    if(empty($fieldDefinition))
    {
      return [];
    }
    else
    {
      return $fieldDefinition->getSettings();
    }
  }

  /**
   * Returns the Label based on the Drupal BundleInfo
   *
   * @return string
   */
  public static function getLabel() : string
  {
    $label = '';
    $bundleInfo = static::getBundleInfo();
    if(array_key_exists('label', $bundleInfo))
    {
      $label = $bundleInfo['label'];
    }

    return $label;
  }

  /**
   * Get the Plural Label of this Model
   *
   * @return string
   */
  public static function getPlural() : string
  {
    return empty(static::$plural) ? '' : static::$plural; // todo, find a way to store this aside the label of the model
  }

  /**
   * Returns the BundleKey, this is either the entityType when no bundle is provided (for example with user) or bundle in all other cases
   *
   * @return string
   */
  public static function getBundleKey() : string
  {
    return empty(static::$bundle) ? static::$entityType : static::$bundle;
  }

  /**
   * Returns the Drupal BundleInfo of the entityType
   *
   */
  public static function getBundleInfo()
  {
    $bundleInfo = \Drupal::service("entity_type.bundle.info")->getBundleInfo(static::$entityType);
    return $bundleInfo[static::getBundleKey()];
  }

  /**
   * Checks whether an underscored field exists on this model, this is to correctly translate a field like first_name back to field_first_name
   *
   * @param string $underscoredField
   * @return boolean
   */
  public static function underScoredFieldExists(string $underscoredField) : bool
  {
    $prettyField = static::getPrettyFieldForUnderscoredField($underscoredField);
    return static::prettyFieldExists($prettyField);
  }

  /**
   * Safe check to see if a getterMethod exists on the model. This shields any platform functions and only checks for functions on the lowest level of abstraccion
   * This is used by SimpleModelWrapper
   *
   * @param Model $model
   * @param string $property
   * @return boolean
   */
  public static function getterExists(Model $model, string $property) : bool
  {
    $getterName = 'get'.$property;
    if(!empty($property) && is_callable(array($model, $getterName)))
    {
      $reflector = new \ReflectionMethod($model, $getterName);
      $isProto = ($reflector->getDeclaringClass()->getName() !== get_class($model));

      return !$isProto; // Meaning the function may only exist on the child class (shielding Model class functions from this)
    }

    return false;
  }

  /**
   * Call a getter function on the model. This is used by SimpleModelWrapper, in combination with getterExists to safely call getters on the lowest level of abstraction (the most child method)
   *
   * @param string $property
   */
  public function callGetter(string $property)
  {
    $getterName = 'get'.$property;
    return $this->$getterName();
  }

  /**
   * Get the Pretty Field for an Underscored Field (for example translates first_name to first-name)
   *
   * @param string $underscoredField
   * @return void
   */
  public static function getPrettyFieldForUnderscoredField(string $underscoredField)
  {
    return str_replace('_', '-', $underscoredField);;
  }

  /**
   * Magic getter implementation of Model, this checks whether the property exists or the relationship,
   *
   * @param [type] $property
   * @return void
   */
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
    else if(static::hasRelationship($property))
    {
      return $this->get($property);
    }
  }

  /**
   * used in combination with the magic getter to get values dynamically by twig templates
   *
   * @param [type] $property
   * @return boolean
   */
  public function __isset($property)
  {
    // Needed for twig to be able to access relationship via magic getter
    return property_exists($this, $property) || array_key_exists($property, $this->relatedViaFieldOnEntity) || array_key_exists($property, $this->relatedViaFieldOnExternalEntity) || static::hasRelationship($property);
  }

  /**
   * This method is executed by ModelApiHandlers before validation takes place. (this gives you the opportunity to fill in required fields before validation)
   *
   * @return void
   */
  public function beforeValidate(){}

  /**
   * This trigger is executed by the Drupal platform before the insertion of the entity takes place.
   * this gives you the opportunity to change values on the entity before being persisted to the database
   *
   * @return void
   */
  public function beforeInsert(){}

  /**
   * This trigger is executed by the Drupal platform after the insertion of the entity to the database has been done
   *
   * @return void
   */
  public function afterInsert(){}

  /**
   * This trigger is executed by the Drupal platform before the update of the entity takes place.
   * this gives you the opportunity to change values on the entity before being persisted to the database
   *
   * @return void
   */
  public function beforeUpdate(){}

  /**
   * This trigger is executed by the Drupal platform after the entity has been updated in the database
   *
   * @return void
   */
  public function afterUpdate(){}

  /**
   * This trigger is executed by the Drupal platform before the entity will be deleted, giving you the opportunity to stop the deletion
   *
   * @return void
   */
  public function beforeDelete(){}

  /**
   * This trigger is executed by the Drupal platform after the entity has been deleted, giving you the opportunity to clean up related records
   *
   * @return void
   */
  public function afterDelete(){}

  /**
   * This method will automatically do cascading deletes for relationships (both Field and ReferencedRelationships) that have the cascading defined in the Relationship
   *
   * @return void
   */
  public function doCascadingDeletes()
  {
    $relationships = static::getRelationships();
    foreach($relationships as $relationship)
    {
      if($relationship->cascadingDelete)
      {
        $fetchedRelationship = $this->fetch($relationship->relationshipName);
        if(!empty($fetchedRelationship))
        {
          if($fetchedRelationship instanceof Collection)
          {
            foreach($fetchedRelationship as $model)
            {
              $model->delete();
            }
          }
          else if($fetchedRelationship instanceof Model)
          {
            $fetchedRelationship->delete();
          }
        }
      }
    }
  }

  /**
   * Checks if there is a Model Class defined for the Entity / Bundle
   *
   * @param string $entity
   * @param string|null $bundle
   * @return boolean
   */
  public static function hasModelClassForEntityAndBundle(string $entity, ?string $bundle) : bool
  {
    static::setModelClassMappings();

    $key = Model::getKeyForEntityAndBundle($entity, $bundle);
    return array_key_exists($key, static::$modelClassMapping);
  }


  /**
   * Returns the fully qualified classname for the provided entity/bundle
   *
   * @param string $entity
   * @param string|null $bundle
   * @return string
   */
  public static function getModelClassForEntityAndBundle(string $entity, ?string $bundle) : string
  {
    if(static::hasModelClassForEntityAndBundle($entity, $bundle))
    {
      $key = Model::getKeyForEntityAndBundle($entity, $bundle);
      return static::$modelClassMapping[$key];
    }
    else
    {
      throw new ModelClassNotDefinedException('No model class for entity '.$entity.' and bundle '.$bundle.' has been defined');
    }
  }

  /**
   * Returns the ModelService that is responsible for the registration of Model Classes in the system
   * This should be implemented by every drupal installation using Spectrum (see ModelServiceInterface for documentation)
   *
   * @return ModelServiceInterface
   */
  public static function getModelService() : ModelServiceInterface
  {
    if(!\Drupal::hasService('spectrum.model'))
    {
      throw new NotImplementedException('No model service found in the Container, please create a custom module, register a service and implement \Drupal\spectrum\Model\ModelServiceInterface');
    }

    $modelService = \Drupal::service('spectrum.model');
    if(!($modelService instanceof ModelServiceInterface))
    {
      throw new NotImplementedException('Model service must implement \Drupal\spectrum\Model\ModelServiceInterface');
    }

    return $modelService;
  }

  /**
   * Returns an Array of all registered Model Classes in the system. (see ModelServiceInterface for documentation)
   *
   * @return array
   */
  public static function getModelClasses() : array
  {
    $modelService = static::getModelService();
    return $modelService->getRegisteredModelClasses();
  }

  /**
   * Find a modelclass by its bundle
   *
   * @param string $bundle
   * @return string|null
   */
  public static function getModelClassByBundle(string $bundle) : ?string
  {
    $foundModelClass = null;
    foreach(static::getModelClasses() as $modelClass)
    {
      if($modelClass::$bundle === $bundle || (empty($modelClass::$bundle) && $modelClass::$entityType === $bundle))
      {
        $foundModelClass = $modelClass;
      }
    }

    return $foundModelClass;
  }

  /**
   * This method will set an array on the abstract Model object, with all the registered models in.
   *
   * @return void
   */
  private static function setModelClassMappings()
  {
    if(static::$modelClassMapping === null)
    {
      static::$modelClassMapping = array();

      foreach(static::getModelClasses() as $modelClassName)
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

  /**
   * Get a unique key for this model class
   *
   * @param string $entity
   * @param string|null $bundle
   * @return string
   */
  public static function getKeyForEntityAndBundle(string $entity, ?string $bundle) : string
  {
    return empty($bundle) ? $entity.'.'.$entity : $entity.'.'.$bundle;
  }

  /**
   * Returns the base permission key in the form of "entity_bundle" (for example node_article) this is used for the permission checker
   *
   * @return string
   */
  public static function getBasePermissionKey() : string
  {
    return str_replace('.', '_', static::getKeyForEntityAndBundle(static::$entityType, static::$bundle));
  }

  public static function getReadPermissionKey() : string
  {
    $permissionKey = static::getBasePermissionKey();
    return 'spectrum api ' . $permissionKey.' read';
  }

  public static function getCreatePermissionKey() : string
  {
    $permissionKey = static::getBasePermissionKey();
    return 'spectrum api ' . $permissionKey.' create';
  }

  public static function getDeletePermissionKey() : string
  {
    $permissionKey = static::getBasePermissionKey();
    return 'spectrum api ' . $permissionKey.' delete';
  }

  public static function getEditPermissionKey() : string
  {
    $permissionKey = static::getBasePermissionKey();
    return 'spectrum api ' . $permissionKey.' edit';
  }

  /**
   * Returns the Registered Permission Service in the Container
   *
   * @return PermissionServiceInterface
   */
  public static function getPermissionsService() : PermissionServiceInterface
  {
    if(!\Drupal::hasService('spectrum.permissions'))
    {
      throw new NotImplementedException('No permissions service found in the Container, please create a custom module, register the service "spectrum.permissions" and implement \Drupal\spectrum\Permissions\PermissionServiceInterface');
    }

    $permissionService = \Drupal::service('spectrum.permissions');
    if(!($permissionService instanceof PermissionServiceInterface))
    {
      throw new NotImplementedException('Permissions service must implement \Drupal\spectrum\Permissions\PermissionServiceInterface');
    }

    return $permissionService;
  }

  /**
   * Check if the current logged in user has permission for this model
   *
   * @param string $access (either C, R, U or D)
   * @return boolean
   */
  private static function currentUserHasPermission(string $access) : bool
  {
    $currentUser = User::loggedInUser();
    return $currentUser->hasModelPermission(get_called_class(), $access);
  }

  /**
   * Checks whether the current logged in user has the Read permission on this Model
   *
   * @return boolean
   */
  public static function userHasReadPermission() : bool
  {
    return static::currentUserHasPermission('R');
  }

  /**
   * Checks whether the current logged in user has the Create permission on this Model
   *
   * @return boolean
   */
  public static function userHasCreatePermission() : bool
  {
    return static::currentUserHasPermission('C');
  }

  /**
   * Checks whether the current logged in user has the Edit/Update permission on this Model
   *
   * @return boolean
   */
  public static function userHasEditPermission() : bool
  {
    return static::currentUserHasPermission('U');
  }

  /**
   * Checks whether the current logged in user has the Delete permission on this Model
   *
   * @return boolean
   */
  public static function userHasDeletePermission() : bool
  {
    return static::currentUserHasPermission('D');
  }
}
