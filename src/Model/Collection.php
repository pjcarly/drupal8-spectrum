<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Query;
use Drupal\spectrum\Query\BundleQuery;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Exceptions\InvalidTypeException;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiDataNode;

/**
 * A collection holds multiple models. It also tracks models that were removed from the collection between the initialization and save.
 * So deletes can be done for models that were removed.
 * It can be used to fetch, validate and save related records defined by Relationships on Models
 *
 * A collection respects the UnitOfWork design pattern. Together with Model and Relationship, this is the Core of the Spectrum framework
 * This functionality is loosly based on BookshelfJS (http://bookshelfjs.org/)
 */
class Collection implements \IteratorAggregate, \Countable
{
  private static $newKeyIndex = 0;

  public $modelType;
  public $models;
  public $originalModels;

  public function __construct()
  {
    $this->models = [];
    $this->originalModels = [];
  }

  /**
   * Implementation of the \Countable interface, returns the amount of Models in this collection
   *
   * @return int
   */
  public function count()
  {
    return sizeof($this->models);
  }

  /**
   * Implementation of the \IteratorAggregate interface, this makes the collection loopable in a php loop
   *
   * @return \ArrayIterator
   */
  public function getIterator()
  {
    // This function makes it possible to loop over a collection, we are just passing the $models as the loopable array
    return new \ArrayIterator($this->models);
  }

  /**
   * Replace the key of the model in the models and originalModels arrays with a new value
   *
   * @param [type] $oldKey
   * @param [type] $newKey
   * @return Collection
   */
  public function replaceOldModelKey($oldKey, $newKey) : Collection
  {
    if(array_key_exists($oldKey, $this->models))
    {
      $model = $this->models[$oldKey];
      unset($this->models[$oldKey]);
      $this->models[$newKey] = $model;
    }

    if(array_key_exists($oldKey, $this->originalModels))
    {
      $originalModel = $this->originalModels[$oldKey];
      unset($this->originalModels[$oldKey]);
      $this->originalModels[$newKey] = $originalModel;
    }

    return $this;
  }

  /**
   * This function saves either all the models in this collection, or if a relationshipName was passed get the relationship, and perform save on that relationship
   *
   * @param string $relationshipName
   * @return Collection
   */
  public function save(string $relationshipName = NULL) : Collection
  {
    if(empty($relationshipName))
    {
      foreach($this->models as $model)
      {
        $model->save();
      }

      foreach($this->getModelsToDelete() as $modelToDelete)
      {
        $modelToDelete->delete();
      }
    }
    else
    {
      $this->get($relationshipName)->save();
    }

    return $this;
  }

  /**
   * This function loads the translation on all the models in this collection, the first found translation will be used on the model. In case no translation is found, the default language will be loaded
   * If not all models have a translation, it is possible that you get models in different languages
   *
   * @param String[] $languageCodes an array containing the languagecodes you want to load on the entity
   * @return Collection
   */
  public function loadTranslation(array $languageCodes) : Collection
  {
    foreach($this->models as $model)
    {
      $model->loadTranslation($languageCodes);
    }

    return $this;
  }

  /**
   * Sort the collection according to a sorting function on the implemented Models
   *
   * @param string $sortingFunction
   * @return Collection
   */
  public function sort(string $sortingFunction) : Collection
  {
    // Bug in PHP causes PHP warnings for uasort, we surpressed warnings with @, but be weary!
    @uasort($this->models, [$this->modelType, $sortingFunction]);
    return $this;
  }

  /**
   * Return the models that were removed from the collection in order to delete them from the database
   *
   * @return array
   */
  public function getModelsToDelete() : array
  {
    $existingRemovedModels = [];
    $removedModelKeys = array_diff(array_keys($this->originalModels), array_keys($this->models));

    foreach($removedModelKeys as $removedModelKey)
    {
      $removedModel = $this->originalModels[$removedModelKey];
      if(!$removedModel->isNew())
      {
        $existingRemovedModels[$removedModel->key] = $removedModel;
      }
    }

    return $existingRemovedModels;
  }


  /**
   * Remove a model by key from the collection
   *
   * @param string $key
   * @return Collection
   */
  public function remove($key) : Collection
  {
    if(array_key_exists($key, $this->models))
    {
      unset($this->models[$key]);
    }

    return $this;
  }

  /**
   * Remove the Model from the Collection
   *
   * @param Model $model
   * @return Collection
   */
  public function removeModel(Model $model) : Collection
  {
    return $this->remove($model->key);
  }

  /**
   * Remove all the models from the collection
   *
   * @return Collection
   */
  public function removeAll() : Collection
  {
    $this->models = [];

    return $this;
  }

  /**
   * Validate all the models in this collection, if a relationshipName was passed get the relationship and validate that
   *
   * @param string $relationshipName
   * @return Validation
   */
  public function validate(string $relationshipName = NULL) : Validation
  {
    if(empty($relationshipName))
    {
      $validation = null;
      foreach($this->models as $model)
      {
        if(empty($validation))
        {
          $validation = $model->validate();
        }
        else
        {
          $validation->merge($model->validate());
        }
      }

      return $validation;
    }
    else
    {
      return $this->get($relationshipName)->validate();
    }
  }

  /**
   * Clear the provided relationship of every model in this Collection
   *
   * @param string $relationshipName
   * @return Collection
   */
  public function clear(string $relationshipName) : Collection
  {
    foreach($this->models as $model)
    {
      $model->clear($relationshipName);
    }

    return $this;
  }

  /**
   * fetch a relationshipname from the database
   *
   * @param string $relationshipName
   * @param Query $queryToCopyFrom (optional) add a query to fetch, to limit the amount of results when fetching, all base conditions, conditions and conditiongroups will be add to the fetch query
   * @return Collection the fetched relationship
   */
  public function fetch(string $relationshipName, ?Query $queryToCopyFrom = null) : Collection
  {
    $returnValue = null;
    $lastRelationshipNameIndex = strrpos($relationshipName, '.');

    if(empty($lastRelationshipNameIndex)) // relationship name without extra relationships
    {
      $modelType = $this->getModelType();
      $relationship = $modelType::getRelationship($relationshipName);
      $relationshipQuery = $relationship->getRelationshipQuery();

      // Lets see if we need to copy in some default conditions
      if(!empty($queryToCopyFrom))
      {
        $relationshipQuery->copyConditionsFrom($queryToCopyFrom);
      }

      $relationshipCondition = $relationship->getCondition();

      if($relationship instanceof FieldRelationship)
      {
        $fieldIds = $this->getFieldIds($relationship);
        if(!empty($fieldIds))
        {
          // we set the field ids in the condition, and fetch a collection of models with that id in a field
          $relationshipCondition->value = $fieldIds;
          $relationshipQuery->addCondition($relationshipCondition);
          $referencedEntities = $relationshipQuery->fetch();

          if(!empty($referencedEntities))
          {
            // Here we will build a collection of the entities we fetched
            $referencedModelType = null;
            $referencedRelationship = null; // the inverse relationship
            $referencedCollection = null;
            foreach($referencedEntities as $referencedEntity)
            {
              $referencedModel = null;
              if($relationship->isPolymorphic || empty($referencedModelType))
              {
                // if the relationship is polymorphic we can get multiple bundles, so we must define the modeltype based on the bundle and entity of the current looping entity
                // or if the related modeltype isn't set yet, we must set it once
                $referencedEntityType = $referencedEntity->getEntityTypeId();
                $referencedEntityBundle = null;
                if(isset($referencedEntity->type->target_id))
                {
                  // with all entity references this shouldn't be a problem, however, in case of 'user', this is blank
                  // luckally we handle this correctly in getModelClassForEntityAndBundle
                  $referencedEntityBundle = $referencedEntity->type->target_id;
                }
                $referencedModelType = Model::getModelClassForEntityAndBundle($referencedEntityType, $referencedEntityBundle);

                // lets also check if a collection has been made already, and if not, lets make one (keeping in mind polymorphic relationships)
                if($referencedCollection == null)
                {
                  if($relationship->isPolymorphic)
                  {
                    $referencedCollection = PolymorphicCollection::forgeNew(null);
                  }
                  else
                  {
                    $referencedCollection = Collection::forgeNew($referencedModelType);
                  }
                }
              }
              // we can finally forge a new model
              $referencedModel = $referencedModelType::forgeByEntity($referencedEntity);
              // and put it in the collection created above
              $referencedCollection->putOriginal($referencedModel);
              $returnValue = $referencedCollection->put($referencedModel);
            }

            $this->putInverses($relationship, $referencedCollection);
          }
        }

        if(empty($returnValue))
        {
          if($relationship->isPolymorphic)
          {
            $returnValue = PolymorphicCollection::forgeNew(null);
          }
          else
          {
            $returnValue = Collection::forgeNew($relationship->modelType);
          }
        }
      }
      else if($relationship instanceof ReferencedRelationship)
      {
        $modelIds = $this->getIds();

        if(!empty($modelIds))
        {
          $relationshipCondition->value = $modelIds;
          $relationshipQuery->addCondition($relationshipCondition);

          $referencingEntities = $relationshipQuery->fetch();

          if(!empty($referencingEntities))
          {
            $referencingCollection = null;
            $referencingModelType = null;
            foreach($referencingEntities as $referencingEntity)
            {
              $referencingModel = null;
              if(empty($referencingModelType))
              {
                // if the relationship is polymorphic we can get multiple bundles, so we must define the modeltype based on the bundle and entity of the current looping entity
                // or if the referencing modeltype isn't set yet, we must set it once
                $referencingEntityType = $referencingEntity->getEntityTypeId();
                $referencingEntityBundle = $referencingEntity->type->target_id;
                $referencingModelType = Model::getModelClassForEntityAndBundle($referencingEntityType, $referencingEntityBundle);

                if($referencingCollection === null)
                {
                  // referencedRelationships are never polymorphics
                  $referencingCollection = Collection::forgeNew($referencingModelType);
                }
              }

              // now that we have a model, lets put them one by one
              $referencingModel = $referencingModelType::forgeByEntity($referencingEntity);
              $referencingCollection->putOriginal($referencingModel);
              $returnValue = $referencingCollection->put($referencingModel);
            }
          }

          if(!empty($referencingCollection))
          {
            $this->putInverses($relationship, $referencingCollection);
          }
        }

        if(empty($returnValue))
        {
          $returnValue = Collection::forgeNew($relationship->modelType);
        }
      }
    }
    else
    {
      $secondToLastRelationshipName = substr($relationshipName, 0, $lastRelationshipNameIndex);
      $resultCollection = $this->get($secondToLastRelationshipName);
      $lastRelationshipName = substr($relationshipName, $lastRelationshipNameIndex+1);
      $returnValue = $resultCollection->fetch($lastRelationshipName, $queryToCopyFrom);
    }

    return $returnValue;
  }

  /**
   * Returns an array with all the IDs of the models in the collection, models without an ID (unsaved models) will not be returned
   *
   * @return array
   */
  public function getIds() : array
  {
    $models = $this->models;

    $ids = [];
    foreach($models as $model)
    {
      $id = $model->getId();
      if(!empty($id))
      {
        $ids[$id] = $id;
      }
    }

    return $ids;
  }

  /**
   * Returns all the ids of a relationship of all the models in this collection
   *
   * @param string $relationship
   * @return array
   */
  public function getFieldIds(FieldRelationship $relationship) : array
  {
    $fieldIds = [];

    foreach($this->models as $model)
    {
      $fieldId = $model->getFieldId($relationship);
      if(!empty($fieldId))
      {
        if(is_array($fieldId))
        {
          $fieldIds = array_merge($fieldId, $fieldIds);
        }
        else
        {
          $fieldIds[$fieldId] = $fieldId;
        }
      }
    }

    return $fieldIds;
  }

  /**
   * Create an Array where the provided fieldName is the key, and the value will be the Model, this only works for fields with a unique value
   * In case multiple models exist with the ame field value, only the last item in the collection with that value will be in the result array
   *
   * @param string $fieldName
   * @return array
   */
  public function buildArrayByFieldName(string $fieldName) : array
  {
    $modelType = $this->modelType;
    $fieldDefinition = $modelType::getFieldDefinition($fieldName);
    $column = null;

    switch($fieldDefinition->getType())
    {
      case 'address':
      case 'geolocation':
        throw new InvalidTypeException('You cant build an array by this field type');
        break;
      case 'entity_reference':
      case 'file':
      case 'image':
        $column = 'target_id';
        break;
      case 'link':
        $column = 'uri';
        break;
      default:
        $column = 'value';
        break;
    }

    $array = [];
    foreach($this->models as $model)
    {
      $key = $model->entity->$fieldName->$column;
      $array[$key] = $model;
    }

    return $array;
  }

  /**
   * Forge a new empty Collection
   *
   * @param string|null $modelType
   * @return Collection
   */
  public static function forgeNew(?string $modelType) : Collection
  {
    return static::forge($modelType, [], [], []);
  }

  /**
   * Forge a new Collection with the ids provided
   *
   * @param string|null $modelType
   * @param array $ids
   * @return Collection
   */
  public static function forgeByIds(?string $modelType, array $ids) : Collection
  {
    return static::forge($modelType, [], [], $ids);
  }

  /**
   * Forge a new Collection with the provided models
   *
   * @param string|null $modelType
   * @param array $models
   * @return Collection
   */
  public static function forgeByModels(?string $modelType, array $models) : Collection
  {
    return static::forge($modelType, $models, [], []);
  }

  /**
   * Forge a new Collection with the provided entities, all entities will be wrapped in a Model
   *
   * @param string|null $modelType
   * @param array $entities
   * @return Collection
   */
  public static function forgeByEntities(?string $modelType, array $entities) : Collection
  {
    return static::forge($modelType, [], $entities, []);
  }

  /**
   * @deprecated
   * Forge a new Collection, try to use the more readable helper methods "forgeByIds", "forgeByModels" or "forgeByEntites" instead
   *
   * @param string $modelType is optional when this is a Polymorphic collection
   * @param array|null $models
   * @param array|null $entities
   * @param array|null $ids
   * @param ModelQuery $modelQuery
   * @return Collection
   */
  private static function forge(string $modelType = null, ?array $models = [], ?array $entities = [], ?array $ids = [], ModelQuery $modelQuery = null) : Collection
  {
    $collection = new static();
    $collection->modelType = $modelType;

    if(is_array($ids) && !empty($ids))
    {
      $entities = static::fetchEntities($modelType, $ids);
    }

    if(is_array($entities) && !empty($entities))
    {
      $models = static::getModels($modelType, $entities);
    }

    if(is_array($models) && !empty($models))
    {
      $collection->putModels($models);
    }

    return $collection;
  }

  private static function fetchEntities(string $modelType, array $ids) : array
  {
    $query = new BundleQuery($modelType::entityType(), $modelType::bundle());

    $query->addCondition(new Condition($modelType::getIdField(), 'IN', $ids));
    return $query->fetch();
  }

  /**
   * Returns an array with all the Entities in the Collection
   *
   * @return array
   */
  public function getEntities() : array
  {
    $entities = [];
    foreach($this->models as $model)
    {
      $id = $model->getId();

      $entity = $model->entity;
      $entities[$id] = $model->entity;
    }

    return $entities;
  }

  private static function getModels(string $modelType, array $entities) : array
  {
    $models = [];
    foreach($entities as $entity)
    {
      $models[] = $modelType::forgeByEntity($entity);
    }
    return $models;
  }

  /**
   * Put all the provided models in the collection
   *
   * @param array $models
   * @return Collection
   */
  private function putModels(array $models) : Collection
  {
    foreach($models as $model)
    {
      $this->put($model);
      $this->putOriginal($model);
    }

    return $this;
  }

  /**
   * Put a Model or Collection in this Collection
   *
   * @param Model|Collection $objectToPut
   * @return Collection
   */
  public function put($objectToPut) : Collection
  {
    if($objectToPut instanceof Collection)
    {
      foreach($objectToPut as $model)
      {
        $this->put($model);
      }

      // Lets loop over the original models as well, we can potentially have original models that arent in the model list.
      foreach($objectToPut->originalModels as $originalModel)
      {
        $this->putOriginal($originalModel);
      }
    }
    else if($objectToPut instanceof Model)
    {
      $model = $objectToPut;
      if(!($model instanceof $this->modelType))
      {
        throw new InvalidTypeException('Model is not of type: '.$this->modelType);
      }

      $this->addModelToModels($model);
    }
    else
    {
      throw new InvalidTypeException('Only objects of type Collection or Model can be put');
    }

    return $this; // we need this to chain fetches, when we put something, we always return the value where the model is being put on, in case of a collection, it is always the collection itself
  }

  /**
   * Put a Model or Collection in this Collection's originalModels
   *
   * @param Model|Collection $objectToPut
   * @return Collection
   */
  public function putOriginal($objectToPut) : Collection
  {
    if($objectToPut instanceof Collection)
    {
      foreach($objectToPut as $model)
      {
        $this->putOriginal($model);
      }
    }
    else if($objectToPut instanceof Model)
    {
      $model = $objectToPut;
      if(!($model instanceof $this->modelType))
      {
        throw new InvalidTypeException('Model is not of type: '.$this->modelType);
      }

      $this->addModelToOriginalModels($model);

    }
    else
    {
      throw new InvalidTypeException('Only objects of type Collection or Model can be put');
    }

    return $this; // we need this to chain fetches, when we put something, we always return the value where the model is being put on, in case of a collection, it is always the collection itself
  }



  /**
   * Create a new Model with the same type as this Collection, put it in the Collection and return it
   *
   * @return Model
   */
  public function putNew() : Model
  {
    $modelType = $this->modelType;
    $newModel = $modelType::forgeNew();
    $this->put($newModel);
    return $newModel;
  }

  /**
   * Add a Model to the model array
   *
   * @param Model $model
   * @return Collection
   */
  protected function addModelToModels(Model $model) : Collection
  {
    if(!array_key_exists($model->key, $this->models))
    {
      $this->models[$model->key] = $model;
    }

    return $this;
  }

  /**
   * Add the provided model to the Original Models array
   *
   * @param Model $model
   * @return Collection
   */
  protected function addModelToOriginalModels(Model $model) : Collection
  {
    if(!array_key_exists($model->key, $this->originalModels))
    {
      $this->originalModels[$model->key] = $model;
    }

    return $this;
  }

  /**
   * Returns the size of the collection
   *
   * @return integer
   */
  public function size() : int
  {
    return count($this->models);
  }

  /**
   * Returns true if this Collection is empty
   *
   * @return boolean
   */
  public function isEmpty() : bool
  {
    return empty($this->models);
  }

  /**
   * Check whether the key of a Model exists in this Collection
   *
   * @param string $key
   * @return boolean
   */
  public function containsKey(string $key) : bool
  {
    return array_key_exists($key, $this->models);
  }

  /**
   * Get a Model by its key from this Collection, returns NULL if the key cannot be found
   *
   * @param string $key
   * @return Model|null
   */
  public function getModel(string $key) : ?Model
  {
    if($this->containsKey($key))
    {
      return $this->models[$key];
    }
    else
    {
      return null;
    }
  }

  /**
   * Returns a Collection with the Models of the provided relationship
   *
   * @param string $relationshipName
   * @return Collection
   */
  public function get(string $relationshipName) : Collection
  {
    $resultCollection;
    $modelType = $this->getModelType();

    $firstRelationshipNameIndex = strpos($relationshipName, '.');

    if(empty($firstRelationshipNameIndex))
    {
      $relationship = $modelType::getRelationship($relationshipName);
      $resultCollection = null;

      if($relationship instanceof ReferencedRelationship)
      {
        $resultCollection = static::forgeNew($relationship->modelType);
      }
      else if($relationship instanceof FieldRelationship)
      {
        if($relationship->isPolymorphic)
        {
          $resultCollection = PolymorphicCollection::forgeNew(null);
        }
        else
        {
          $resultCollection = static::forgeNew($relationship->modelType);
        }
      }
      else
      {
        throw new InvalidTypeException('Invalid Relationship type ');
      }

      foreach($this->models as $model)
      {
        $relationshipModels = $model->get($relationship);

        if(!empty($relationshipModels))
        {
          $resultCollection->put($relationshipModels);
        }
      }
    }
    else
    {
      $firstRelationshipName = substr($relationshipName, 0,  $firstRelationshipNameIndex);
      $newCollection = $this->get($firstRelationshipName);
      $newRelationshipName = substr($relationshipName, $firstRelationshipNameIndex+1);

      $resultCollection = $newCollection->get($newRelationshipName);
    }

    return $resultCollection;
  }

  /**
   * Magic getter that provides helper properties on Collection
   *
   * @param string $property
   * @return object
   */
  public function __get($property)
  {
    if (property_exists($this, $property))
    {
      return $this->$property;
    }
    else // lets check for pseudo properties
    {
      switch($property)
      {
        case "size":
          return $this->size();
          break;
        case "isEmpty":
          return $this->isEmpty();
          break;
        case "entities":
          return $this->getEntities();
          break;
      }
    }
  }

  /**
   * Magic setter that prevents the overriding of the $models and $originalModels properties
   *
   * @param [type] $property
   * @param [type] $value
   */
  public function __set($property, $value)
  {
    switch($property)
    {
      case "models":
      case "originalModels":
      break;

      default:
        if(property_exists($this, $property))
        {
          $this->$property = $value;
        }
        break;
    }

    return $this;
  }

  /**
   * Magic isset method, for use by the Twig rendering engine
   *
   * @param [type] $property
   * @return boolean
   */
  public function __isset($property)
  {
    // Needed for twig to be able to access relationship via magic getter
    return property_exists($this, $property) || in_array($property, ['size', 'isEmpty', 'entities']);
  }

  /**
   * Serializes the collection to a jsonapi.org compliant stdClass
   *
   * @return \stdClass
   */
  public function serialize() : \stdClass
  {
    $root = new JsonApiRootNode();

    $data = $this->getJsonApiNode();
    $root->setData($data);

    return $root->serialize();
  }

  /**
   * Converts the Collection to a JsonApiDataNode
   *
   * @return JsonApiDataNode
   */
  public function getJsonApiNode() : JsonApiDataNode
  {
    $data = new JsonApiDataNode();

    foreach($this->models as $model)
    {
      $node = $model->getJsonApiNode();
      $data->addNode($node);
    }

    return $data;
  }

  /**
   * This method sets the provided collection as the inverse for the provided relationship
   *
   * @param Relationship $relationship
   * @param Collection $inverses
   * @return void
   */
  private function putInverses(Relationship $relationship, Collection $inverses) : void
  {
    $relationshipName = null;
    $referencingCollection = null;
    $referencedCollection = null;

    if($relationship instanceof FieldRelationship)
    {
      $relationshipName = $relationship->relationshipName;
      $referencingCollection = $this;
      $referencedCollection = $inverses;
    }
    else
    {
      $relationshipName = $relationship->fieldRelationship->relationshipName;
      $referencingCollection = $inverses;
      $referencedCollection = $this;
    }

    /** @var Model $referencingModel */
    foreach($referencingCollection as $referencingModel)
    {
      $modelFieldRelationship = $referencingModel::getRelationship($relationshipName);
      $fieldIds = $referencingModel->getFieldId($modelFieldRelationship);

      if(empty($fieldIds))
      {
        $fieldIds = [];
      }

      if(!is_array($fieldIds))
      {
        $fieldIds = [$fieldIds];
      }

      foreach($fieldIds as $fieldId)
      {
        $referencedModel = $referencedCollection->getModel($fieldId);
        $referencingModel->put($modelFieldRelationship, $referencedModel);
      }
    }
  }

  /**
   * Get the value of modelType
   */
  public function getModelType() : string
  {
    return $this->modelType;
  }

  /**
   * Checks if the modeltype of the collection has the provided relationship
   *
   * @param string $relationshipName
   * @return boolean
   */
  public function hasRelationship(string $relationshipName) : bool
  {
    $modelType = $this->getModelType();

    return $modelType::hasRelationship($relationshipName);
  }
}
