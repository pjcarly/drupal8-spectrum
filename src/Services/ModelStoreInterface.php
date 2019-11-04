<?php

namespace Drupal\spectrum\Services;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Collection;


/**
 * The ModelStore is a datastore service which can be used to cache Models.
 */
interface ModelStoreInterface
{
  /**
   * Finds a single Model by a value of a provided fieldname. If multiple Models have the same value, the first one is returned
   *
   * @param string $modelClass
   * @param string $fieldName Name of the Field
   * @param string $value Value of the Field
   * @return Model|null
   */
  public function getModelByFieldValue(string $modelClass, string $fieldName, string $value): ?Model;

  /**
   * Finds all Models in the store by a provided field value
   *
   * @param string $modelClass
   * @param string $fieldName Name of the Field
   * @param string $value Value of the Field
   * @return Collection
   */
  public function getCollectionByFieldValue(string $modelClass, string $fieldName, string $value): Collection;

  /**
   * Adds a Model to the DataStore
   *
   * @param Model $model
   * @return self
   */
  public function addModel(Model $model): ModelStoreInterface;

  /**
   * Add every Model in the Collection to the datastore
   *
   * @param Collection $collection
   * @return self
   */
  public function addCollection(Collection $collection): ModelStoreInterface;

  /**
   * Clears the entire model store of all the data
   *
   * @return self
   */
  public function clearAll(): ModelStoreInterface;
}
