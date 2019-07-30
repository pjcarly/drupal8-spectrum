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
   * Finds a Model by a value of a provided fieldname
   *
   * @param string $modelClass
   * @param string $fieldName Name of the Field
   * @param string $value Value of the Field
   * @return Model|null
   */
  public function getByFieldValue(string $modelClass, string $fieldName, string $value): ?Model;

  /**
   * Adds a Model to the DataStore
   *
   * @param Model $model
   * @return self
   */
  public function addModel(Model $model): ModelStore;

  /**
   * Add every Model in the Collection to the datastore
   *
   * @param Collection $collection
   * @return self
   */
  public function addCollection(Collection $collection): ModelStore;
}
