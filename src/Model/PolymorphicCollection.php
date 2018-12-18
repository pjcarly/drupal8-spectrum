<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Query;
use Drupal\spectrum\Exceptions\PolymorphicException;

/**
 * This class provides an extensions of the Collection class, where in a regular collection only 1 type of Model can be hold.
 * A polymorphic Collection can hold multiple model types.
 * This is used when an Entity Reference can refer to multiple different entities
 *
 * This class has limitations, not all functionality of a regular collection works
 */
class PolymorphicCollection extends Collection
{
  /**
   * The entity type allowed in this Collection (only models with the same entity type allowed), The first added entitytype of a model is used
   *
   * @var string
   */
  private $entityType;

  /**
   * This method gives you the ability to save all models in the collection.
   * Passing in a relationshipName does not work, and will throw an Exception, it has no meaning for polymorphic Collections
   *
   * @param string $relationshipName DO NOT USE
   * @return Collection
   */
  public function save(string $relationshipName = NULL) : Collection
  {
    if(!empty($relationshipName))
    {
      throw new PolymorphicException('Relationship path "'.$relationshipName.'" has no meaning for polymorphic collections');
    }

    return parent::save();
  }

  /**
   * This method gives you the ability to validate all models in the collection.
   * Passing in a relationshipName does not work, and will throw an Exception, it has no meaning for polymorphic Collections
   *
   * @param string $relationshipName DO NOT USE
   * @return Validation
   */
  public function validate(string $relationshipName = NULL) : Validation
  {
    if(!empty($relationshipName))
    {
      throw new PolymorphicException('Relationship path "'.$relationshipName.'" has no meaning for polymorphic collections');
    }

    return parent::validate();
  }


  /**
   * @deprecated
   * Fetching does not work on Polymorphic Collections, this will throw an Exception
   *
   * @param string $relationshipName
   * @return Collection
   */
  public function fetch(string $relationshipName, ?Query $queryToCopyFrom = null) : Collection
  {
    throw new PolymorphicException('Fetch has no meaning for polymorphic collections');
  }

  /**
   * Puts a new Model or a Collection in a Polymorphic Collection, these can be different model types, however they must be of the same Entity.
   * In Drupal an Entity Reference field, can reference every bundle in the same Entity, but not different Entities.
   * For Example, you can put a "node_article" and a "node_basic_page" in the same Polymorphic Collection, but no "node_article" and a "user"
   *
   * @param object $objectToPut
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
    }
    else
    {
      $model = $objectToPut;
      // it is only possible to have models with a shared entity in a collection
      if(empty($this->entityType))
      {
        $this->entityType = $model::entityType();
      }
      else if($this->entityType !== $model::entityType())
      {
        throw new PolymorphicException('Only models with a shared entity type are allowed in a polymorphic collection');
      }

      // due to the the shared entity constraint, the key of polymorphic collections is unique,
      // because in drupal ids are unique over different bundles withing the same entity
      // so we can just use the parent addModelToModels and addModelToOriginalModels function, we won't have any conflicts there
      $this->addModelToModels($model);
    }

    return $this;
  }

  /**
   * Puts a new Model or a Collection in a Polymorphic Collection, these can be different model types, however they must be of the same Entity.
   * In Drupal an Entity Reference field, can reference every bundle in the same Entity, but not different Entities.
   * For Example, you can put a "node_article" and a "node_basic_page" in the same Polymorphic Collection, but no "node_article" and a "user"
   *
   * @param object $objectToPut
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
    else
    {
      $model = $objectToPut;
      // it is only possible to have models with a shared entity in a collection
      if(empty($this->entityType))
      {
        $this->entityType = $model::entityType();
      }
      else if($this->entityType !== $model::entityType())
      {
        throw new PolymorphicException('Only models with a shared entity type are allowed in a polymorphic collection');
      }

      // due to the the shared entity constraint, the key of polymorphic collections is unique,
      // because in drupal ids are unique over different bundles withing the same entity
      // so we can just use the parent addModelToModels and addModelToOriginalModels function, we won't have any conflicts there
      $this->addModelToOriginalModels($model);
    }

    return $this;
  }

  /**
   * @deprecated
   * PutNew doesnt work for polymporhic collections, as it is unknown what type should be created.
   *
   * @return Model
   */
  public function putNew() : Model
  {
    throw new PolymorphicException('PutNew has no meaning for polymorphic collections, we can\'t know the type of model to create');
  }

  /**
   * @deprecated
   * Getting items doesnt work on a Polymorhpic Collection and will throw an Exception
   *
   * @param string $relationshipName
   * @return Collection
   */
  public function get(string $relationshipName) : Collection
  {
    throw new PolymorphicException('Get has no meaning for polymorphic collections');
  }

  /**
   * @deprecated
   * Checking if a relationship exists on a Polymorphic Collection has no meaning, it will throw an Exception
   *
   * @param string $relationshipName
   * @return boolean
   */
  public function hasRelationship(string $relationshipName) : bool
  {
    throw new PolymorphicException('hasRelationship has no meaning for polymorphic collections');
  }
}
