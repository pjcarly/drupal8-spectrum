<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Model\Model;

/**
 * Class EntityQuery
 *
 * An EntityQuery, is the most basic query around, it provides a way of querying results with a certain entity. (multiple bundles can be returned)
 *
 * @package Drupal\spectrum\Query
 */
class EntityQuery extends Query
{
  /**
   * Execute the query, and fetch a single Model, if multiple entities are found, the first one is returned. If nothing is found, null is returend
   *
   * @return \Drupal\spectrum\Model\Model|null
   */
  public function fetchSingleModel() : ?Model
  {
    $entity = $this->fetchSingle();
    return isset($entity) ? Model::forgeByEntity($entity) : NULL;
  }

}
