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
   * Finds a Model by a value of a provided fieldname
   *
   * @param string $modelClass
   * @param string $fieldName Name of the Field
   * @param string $value Value of the Field
   * @return Model|null
   */
  public function getByFieldValue(string $modelClass, string $fieldName, string $value = null) : ?Model
  {
    $model = null;

    if(!empty($value) && array_key_exists($modelClass, $this->data))
    {
      foreach($this->data[$modelClass] as $cachedModel)
      {
        if($cachedModel->entity->$fieldName->value === $value)
        {
          $model = $cachedModel;
          break;
        }
      }
    }

    return $model;
  }

  /**
   * Adds a Model to the DataStore
   *
   * @param Model $model
   * @return self
   */
  public function addModel(Model $model) : ModelStore
  {
    $modelClass = $model->getModelName();

    if(!array_key_exists($modelClass, $this->data))
    {
      $this->data[$modelClass] = [];
    }


    $this->data[$modelClass][$model->key] = $model;
    return $this;
  }

  /**
   * Add every Model in the Collection to the datastore
   *
   * @param Collection $collection
   * @return self
   */
  public function addCollection(Collection $collection) : ModelStore
  {
    foreach($collection as $model)
    {
      $this->addModel($model);
    }

    return $this;
  }
}