<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Query;

/**
 * Interface ModelInterface
 *
 * @package Drupal\spectrum\Model
 */
interface ModelInterface {

  /**
   * @param string|NULL $relationshipName
   *
   * @return \Drupal\spectrum\Model\Model
   */
  public function save(string $relationshipName = NULL): Model;

  /**
   * @param string $relationshipName
   * @param \Drupal\spectrum\Query\Query|null $queryToCopyFrom
   *
   * @return mixed
   */
  public function fetch(string $relationshipName, ?Query $queryToCopyFrom = null);

  /**
   * @param string $relationshipName
   * @return Collection|Model|null
   */
  public function get(string $relationshipName);

}
