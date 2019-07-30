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
   * @param \Drupal\spectrum\Model\Model $model
   */
  public function onSave(Model $model): void;

  /**
   * @param \Drupal\spectrum\Model\Model $model
   */
  public function onDelete(Model $model): void;

  /**
   * @param \Drupal\Core\Database\Query\Select $query
   *
   * @return \Drupal\Core\Database\Query\Select
   */
  public function onQuery(Select $query): Select;

  /**
   * @param \Drupal\spectrum\Model\Model $model
   * @param int $uid
   *
   * @return bool
   */
  public function userHasAccess(Model $model, int $uid): bool;
}