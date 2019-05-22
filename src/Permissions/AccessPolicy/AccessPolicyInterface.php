<?php

namespace Drupal\spectrum\Permissions\AccessStrategy;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\spectrum\Model\Model;

/**
 * Interface AccessPolicyInterface
 *
 * @package Drupal\spectrum\Permissions\AccessStrategy
 */
interface AccessPolicyInterface {

  /**
   * Called when a model is saved.
   *
   * @param \Drupal\spectrum\Model\Model $model
   */
  public function onSave(Model $model): void;

  /**
   * @param \Drupal\Core\Database\Query\AlterableInterface $query
   *
   * @return \Drupal\Core\Database\Query\AlterableInterface
   */
  public function onQuery(AlterableInterface $query): AlterableInterface;

}
