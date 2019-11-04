<?php

namespace Drupal\spectrum\Services;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Collection;


/**
 * The ModelStore is a datastore service which can be used to cache Models.
 */
class ModelStore implements ModelStoreInterface
{
  /**
   * This array holds the ModelStore data
   *
   * @var array
   */
  private $data = [];

  /**
   * {@inheritdoc}
   */
  public function getModelByFieldValue(string $modelClass, string $fieldName, string $value = null): ?Model
  {
    $model = null;

    if (!empty($value) && array_key_exists($modelClass, $this->data)) {
      foreach ($this->data[$modelClass] as $cachedModel) {
        if ($cachedModel->entity->{$fieldName}->value === $value) {
          $model = $cachedModel;
          break;
        }
      }
    }

    return $model;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionByFieldValue(string $modelClass, string $fieldName, string $value): Collection
  {
    $collection = Collection::forgeNew($modelClass);

    if (!empty($value) && array_key_exists($modelClass, $this->data)) {
      foreach ($this->data[$modelClass] as $cachedModel) {
        if ($cachedModel->entity->{$fieldName}->value === $value) {
          $collection->put($cachedModel);
        }
      }
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function addModel(Model $model): ModelStoreInterface
  {
    $modelClass = $model->getModelName();

    if (!array_key_exists($modelClass, $this->data)) {
      $this->data[$modelClass] = [];
    }


    $this->data[$modelClass][$model->key] = $model;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addCollection(Collection $collection): ModelStoreInterface
  {
    foreach ($collection as $model) {
      $this->addModel($model);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clearAll(): ModelStoreInterface
  {
    $this->data = [];
    return $this;
  }
}
