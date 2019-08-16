<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\Select;
use Drupal\spectrum\Model\Model;

/**
 * Interface AccessPolicyInterface
 *
 * @package Drupal\spectrum\Permissions\AccessPolicy
 */
interface AccessPolicyInterface
{

  /**
   * @var string
   */
  const TABLE_ENTITY_ACCESS = 'spectrum_entity_access';

  /**
   * Called when a model is saved.
   *
   * @param Model $model
   */
  public function onSave(Model $model): void;

  /**
   * @param Model $model
   */
  public function onDelete(Model $model): void;

  /**
   * @param Select $query
   *
   * @return Select
   */
  public function onQuery(Select $query): Select;

  /**
   * @param Model $model
   * @param int $uid
   *
   * @return bool
   */
  public function userHasAccess(Model $model, int $uid): bool;

  /**
   * This function is called whenever a change to a model is picked up by the platform.
   * The implementation of the Access Policy should return TRUE when the access policy should be recalculated
   * And false if it shouldnt
   *
   * @param Model $model
   * @return boolean
   */
  public function shouldSetAccessPolicy(Model $model): bool;
}
