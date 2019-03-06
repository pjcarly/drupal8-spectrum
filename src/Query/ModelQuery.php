<?php

namespace Drupal\spectrum\Query;

use Drupal\gds\Data\ChunkedIterator;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\Model;

/**
 * The ModelQuery is an extension of a regular query, with extra methods to either directly return a Model or a Collection
 */
class ModelQuery extends BundleQuery
{
  /**
   * The fully qualified classname of the modeltype you are querying
   *
   * @var string
   */
  public $modelType;

  /**
   * @param string $modelType The fully qualified classname of the modeltype you are querying, the entity type and bundle will be pulled from the model class
   */
  public function __construct(string $modelType)
  {
    parent::__construct($modelType::entityType(), $modelType::bundle());
    $this->modelType = $modelType;
  }

  /**
   * Execute the query, and return a collection with all the found entities
   *
   * @return Collection
   */
  public function fetchCollection() : Collection
  {
    $entities = $this->fetch();
    return Collection::forgeByEntities($this->modelType, $entities);
  }

  /**
   * @return \Generator
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function fetchGenerator(): \Generator {
    $storage = \Drupal::entityTypeManager()->getStorage($this->entityType);
    $entities = new ChunkedIterator($storage, $this->fetchIds());

    foreach ($entities as $entity) {
      yield $this->modelType::forgeByEntity($entity);
    }
  }

  /**
   * Execute the query, and fetch a single Model, if multiple entities are found, the first one is returned. If nothing is found, null is returend
   *
   * @return Model|null
   */
  public function fetchSingleModel() : ?Model
  {
    $entity = $this->fetchSingle();

    if($entity != null)
    {
      $modelType = $this->modelType;
      return $modelType::forgeByEntity($entity);
    }
    else
    {
      return null;
    }
  }
}
