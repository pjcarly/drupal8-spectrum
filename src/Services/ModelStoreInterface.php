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
   * Finds a single Model by a value of a provided fieldname. First search the store,
   * if a model is present that will be returned, if it isnt present, a query will be done
   * the model will be put in the store and returned.
   * If multiple Models have the same value, the first one is returned
   *
   * @param string $modelClass
   * @param string $fieldName Name of the Field
   * @param string $value Value of the Field
   * @return Model|null
   */
  public function findRecordByFieldValue(string $modelClass, string $fieldName, string $value): ?Model;

  /**
   * Does a query for all the records with a given field value, puts them in the store,
   * and returns the results
   *
   * @param string $modelClass
   * @param string $fieldName Name of the Field
   * @param string $value Value of the Field
   * @return Collection
   */
  public function findRecordsByFieldValue(string $modelClass, string $fieldName, string $value): Collection;

  /**
   * Queries all the records of a given modelclass from the database, puts them in the store,
   * and returns the results
   *
   * @param string $modelClass
   * @return Collection
   */
  public function findAll(string $modelClass): Collection;

  /**
   * Searches the store for a model with a specific ID, if it isnt found, a Query will be done
   * The model should be queried, put in the store, and returned
   *
   * @param string $modelClass
   * @param string $id
   * @return Model|null
   */
  public function findRecord(string $modelClass, string $id): ?Model;

  /**
   * Returns all the Models currently in the Model Store for a given ModelClass
   *
   * @param string $modelClass
   * @return Collection
   */
  public function peekAll(string $modelClass): Collection;

  /**
   * Look for a Model in the store (in memory)
   *
   * @param string $modelClass
   * @param string $id
   * @return Model|null
   */
  public function peekRecord(string $modelClass, string $id): ?Model;

  /**
   * Finds a single Model by a value of a provided fieldname. If multiple Models have the same value, the first one is returned
   *
   * @param string $modelClass
   * @param string $fieldName Name of the Field
   * @param string $value Value of the Field
   * @return Model|null
   */
  public function peekRecordByFieldValue(string $modelClass, string $fieldName, string $value): ?Model;

  /**
   * Finds all Models in the store by a provided field value
   *
   * @param string $modelClass
   * @param string $fieldName Name of the Field
   * @param string $value Value of the Field
   * @return Collection
   */
  public function peekRecordsByFieldValue(string $modelClass, string $fieldName, string $value): Collection;

  /**
   * Adds a Model to the DataStore
   *
   * @param Model $model
   * @return self
   */
  public function pushModel(Model $model): ModelStoreInterface;

  /**
   * Add every Model in the Collection to the datastore
   *
   * @param Collection $collection
   * @return self
   */
  public function pushCollection(Collection $collection): ModelStoreInterface;

  /**
   * Clears the entire model store of all the data
   *
   * @return self
   */
  public function unloadAll(): ModelStoreInterface;

  /**
   * Unloads the provided Model from the Store
   *
   * @param Model $model
   * @return ModelStoreInterface
   */
  public function unloadRecord(Model $model): ModelStoreInterface;
}
