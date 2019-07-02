<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\Select;
use Drupal\spectrum\Model\Model;

/**
 * Interface AccessPolicyInterface
 *
 * @package Drupal\spectrum\Permissions\AccessPolicy
 */
interface AccessPolicyInterface {

  /**
   * Called when a model is saved.
   *
   * @param \Drupal\spectrum\Model\Model $model
   */
  public function onSave(Model $model): void;

  /**
   * @param \Drupal\Core\Database\Query\Select $query
   *
   * @return \Drupal\Core\Database\Query\Select
   */
  public function onQuery(Select $query): Select;

}
