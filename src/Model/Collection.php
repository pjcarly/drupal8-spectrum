<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Exceptions\InvalidTypeException;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiDataNode;

class Collection implements \IteratorAggregate
{
	private static $newKeyIndex = 0;

	public $modelType;
	public $models;
	public $originalModels;

	public function __construct()
	{
		$this->models = array();
		$this->originalModels = array();
	}

  public function getIterator()
  {
    // This function makes it possible to loop over a collection, we are just passing the $models as the loopable array
    return new \ArrayIterator($this->models);
  }

	public function save($relationshipName = NULL)
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
	}

  public function sort($sortingFunction)
  {
    // Bug in PHP causes PHP warnings for uasort, we surpressed warnings with @, but be weary!
    @uasort($this->models, array($this->modelType, $sortingFunction));
  }

  public function getModelsToDelete()
  {
    $existingRemovedModels = array();
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

  public function remove($key)
  {
    if(array_key_exists($key, $this->models))
    {
      unset($this->models[$key]);
    }
  }

  public function removeAll()
  {
    $this->models = array();
  }

  public function validate($relationshipName = NULL)
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

	public function fetch($relationshipName)
	{
		$lastRelationshipNameIndex = strrpos($relationshipName, '.');

		if(empty($lastRelationshipNameIndex)) // relationship name without extra relationships
		{
			$modelType = $this->modelType;
			$relationship = $modelType::getRelationship($relationshipName);

			$relationshipQuery = $relationship->getRelationshipQuery();
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

                // we must also find the inverse relationship to put the current model on
                $referencedRelationship = $referencedModelType::getReferencedRelationshipForFieldRelationship($relationship);

                // lets also check if a collection has been made already, and if not, lets make one (keeping in mind polymorphic relationships)
                if($referencedCollection == null)
                {
                  if($relationship->isPolymorphic)
                  {
                    $referencedCollection = PolymorphicCollection::forge(null);
                  }
                  else
                  {
                    $referencedCollection = Collection::forge($referencedModelType);
                  }
                }
              }
              // we can finally forge a new model
              $referencedModel = $referencedModelType::forge($referencedEntity);
              // and put it in the collection created above
              $referencedCollection->put($referencedModel, true);
            }

            static::putReferencedCollectionOnReferencingCollection($relationship, $referencedRelationship, $this, $referencedCollection);
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
                  $referencingCollection = Collection::forge($referencingModelType);
                }
              }

              // now that we have a model, lets put them one by one
              $referencingModel = $referencingModelType::forge($referencingEntity);
              $referencingCollection->put($referencingModel, true);
            }
          }

          if(!empty($referencingCollection))
          {
            static::putReferencedCollectionOnReferencingCollection($relationship->fieldRelationship, $relationship, $referencingCollection, $this);
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

	public function getIds()
	{
		$models = $this->models;

		$ids = array();
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

	public function getFieldIds($relationship)
	{
		$fieldIds = array();

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

	public static function forge($modelType, $models = array(), $entities = array(), $ids = array(), $modelQuery = null)
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
			$collection->setModels($models);
		}

		return $collection;
	}

	private static function fetchEntities($modelType, $ids)
	{
		$query = new Query($modelType::$entityType, $modelType::$bundle);
		$entityInfo = entity_get_info($modelType::$entityType);

		$query->addCondition(new Condition($modelType::$idField, 'IN', $ids));
		return $query->fetch();
	}

  public function getEntities()
	{
		$entities = array();
		foreach($this->models as $model)
		{
      $id = $model->getId();

			$entity = $model->entity;
			$entities[$id] = $model->entity;
		}

		return $entities;
	}

	private static function getModels($modelType, $entities)
	{
		$models = array();
		foreach($entities as $entity)
		{
			$models[] = $modelType::forge($entity);
		}
		return $models;
	}

	private function setModels($models)
	{
		foreach($models as $model)
		{
			$this->put($model, TRUE);
		}
	}

	public function put($objectToPut, $includeInOriginalModels = FALSE)
	{
    if($objectToPut instanceof Collection)
    {
      foreach($objectToPut as $model)
      {
        $this->put($model, $includeInOriginalModels);
      }
    }
    else
    {
      $model = $objectToPut;
      if(!($model instanceof $this->modelType))
  		{
  			throw new InvalidTypeException('Model is not of type: '.$this->modelType);
  		}

      $this->addModelToArrays($model, $includeInOriginalModels);
    }
	}

  public function putNew()
  {
    $modelType = $this->modelType;
    $newModel = $modelType::createNew();
    $this->put($newModel);
    return $newModel;
  }

  protected function addModelToArrays(Model $model, $includeInOriginalModels = FALSE)
  {
    if(!array_key_exists($model->key, $this->models))
		{
			$this->models[$model->key] = $model;

      if($includeInOriginalModels)
      {
        $this->originalModels[$model->key] = $model;
      }
		}
  }

	public function size()
	{
		return count($this->models);
	}

	public function isEmpty()
	{
		return empty($this->models);
	}

	public function containsKey($key)
	{
		return array_key_exists($key, $this->models);
	}

	public function getModel($key)
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

	public function get($relationshipName)
	{
		$resultCollection;
		$modelType = $this->modelType;

		$firstRelationshipNameIndex = strpos($relationshipName, '.');

		if(empty($firstRelationshipNameIndex))
		{
			$relationship = $modelType::getRelationship($relationshipName);
      $resultCollection = null;
      if($relationship->isPolymorphic)
      {
        $resultCollection = PolymorphicCollection::forge(null);
      }
      else
      {
        $resultCollection = static::forge($relationship->modelType);
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

  public function __isset($property)
  {
    // Needed for twig to be able to access relationship via magic getter
    return property_exists($this, $property) || in_array($property, array('size', 'isEmpty', 'entities'));
  }

  public function serialize()
  {
    $root = new JsonApiRootNode();

    $data = $this->getJsonApiNode();
    $root->setData($data);

    return $root->serialize();
  }

  public function getJsonApiNode()
  {
    $data = new JsonApiDataNode();

    foreach($this->models as $model)
    {
      $node = $model->getJsonApiNode();
      $data->addNode($node);
    }

    return $data;
  }

  private static function putReferencedCollectionOnReferencingCollection(FieldRelationship $referencingRelationship, $referencedRelationship, Collection $referencingCollection, Collection $referencedCollection)
  {
    foreach($referencingCollection as $referencingModel)
    {
      $fieldId = $referencingModel->getFieldId($referencingRelationship);
      if(!empty($fieldId))
      {
        if(is_array($fieldId)) // remember, we can also have multiple references in the same field
        {
          foreach($fieldId as $referencedId)
          {
            $referencedModel = $referencedCollection->getModel($referencedId);
            if(!empty($referencedModel))
            {
              if($referencingRelationship->isPolymorphic)
              {
                $referencedModelType = get_class($referencedModel);
                // we must also find the inverse relationship to put the current model on
                $referencedRelationship = $referencedModelType::getReferencedRelationshipForFieldRelationship($referencingRelationship);
              }

              $referencingModel->put($referencingRelationship, $referencedModel);
              if(!empty($referencedRelationship))
              {
                $referencedModel->put($referencedRelationship, $referencingModel);
              }
            }
          }
        }
        else
        {
          $referencedModel = $referencedCollection->getModel($fieldId);
          if(!empty($referencedModel))
          {
            if($referencingRelationship->isPolymorphic)
            {
              $referencedModelType = get_class($referencedModel);
              // we must also find the inverse relationship to put the current model on
              $referencedRelationship = $referencedModelType::getReferencedRelationshipForFieldRelationship($referencingRelationship);
            }

            $referencingModel->put($referencingRelationship, $referencedModel);
            if(!empty($referencedRelationship))
            {
              $referencedModel->put($referencedRelationship, $referencingModel);
            }
          }
        }
      }
    }
  }
}
