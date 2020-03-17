<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\Select;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Models\User;

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
   *
   * @return AccessPolicyInterface
   */
  public function onSave(Model $model): AccessPolicyInterface;

  /**
   * @param Model $model
   *
   * @return AccessPolicyInterface
   */
  public function onDelete(Model $model): AccessPolicyInterface;

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

  /**
   * Rebuilds the Entire Access Policy table for the provided Model Class
   *
   * @param string $modelClass
   * @return void
   */
  public function rebuildForModelClass(string $modelClass): AccessPolicyInterface;

  /**
   * Returns the Access for all the entities of a modelclass
   *
   * @param User $user
   * @return AccessPolicyEntity[]
   */
  public function getUserAccessForModelClass(User $user, string $modelClass): array;

  /**
   * @param string $entityTypeId
   * @param string $entityId
   *
   * @return array
   */
  public function getUserIdsWithAccess(string $entityTypeId, string $entityId): array;
}
