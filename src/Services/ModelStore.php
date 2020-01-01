<?php

namespace Drupal\spectrum\Services;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Query\ModelQuery;

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
  public function findRecordsByFieldValue(string $modelClass, string $fieldName, string $value): Collection
  {
    /** @var ModelQuery $query */
    $query = $modelClass::getModelQuery();
    $query->addCondition(new Condition($fieldName, '=', $value));
    $collection = $query->fetchCollection();

    if (isset($collection)) {
      $this->pushCollection($collection);
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function findRecordByFieldValue(string $modelClass, string $fieldName, string $value): ?Model
  {
    $model = $this->peekRecordByFieldValue($modelClass, $fieldName, $value);

    if (!isset($model)) {
      /** @var ModelQuery $query */
      $query = $modelClass::getModelQuery();
      $query->addCondition(new Condition($fieldName, '=', $value));
      $model = $query->fetchSingleModel();

      if (isset($model)) {
        $this->pushModel($model);
      }
    }

    return $model;
  }

  /**
   * {@inheritdoc}
   */
  public function findAll(string $modelClass): Collection
  {
    /** @var ModelQuery $query */
    $query = $modelClass::getModelQuery();

    $collection = $query->fetchCollection();
    $this->pushCollection($collection);

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function findRecord(string $modelClass, string $id): ?Model
  {
    $model = $this->peekRecord($modelClass, $id);

    if (!isset($model)) {
      $model = $modelClass::forgeById($id);

      if (isset($model)) {
        $this->pushModel($model);
      }
    }

    return $model;
  }

  /**
   * {@inheritdoc}
   */
  public function peekAll(string $modelClass): Collection
  {
    $collection = Collection::forgeNew($modelClass);

    foreach ($this->data[$modelClass] as $model) {
      $collection->put($model);
    }

    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function peekRecord(string $modelClass, string $id): ?Model
  {
    return $this->peekRecordByFieldValue($modelClass, $modelClass::getIdField(), $id);
  }

  /**
   * {@inheritdoc}
   */
  public function peekRecordByFieldValue(string $modelClass, string $fieldName, string $value = null): ?Model
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
  public function peekRecordsByFieldValue(string $modelClass, string $fieldName, string $value): Collection
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
  public function pushModel(Model $model): ModelStoreInterface
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
  public function pushCollection(Collection $collection): ModelStoreInterface
  {
    foreach ($collection as $model) {
      $this->pushModel($model);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unloadAll(): ModelStoreInterface
  {
    $this->data = [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function unloadRecord(Model $model): ModelStoreInterface
  {
    $modelClassName = get_class($model);

    unset($this->data[$modelClassName][$model->key]);

    return $this;
  }
}
