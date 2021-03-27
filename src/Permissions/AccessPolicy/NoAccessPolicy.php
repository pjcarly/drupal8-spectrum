<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\Select;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Models\User;

/**
 * Class NoAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessPolicy
 */
class NoAccessPolicy implements AccessPolicyInterface
{

  /**
   * @inheritDoc
   */
  public function onSave(Model $model): AccessPolicyInterface
  {
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function onDelete(Model $model): AccessPolicyInterface
  {
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function onQuery(Select $query, int $userId): Select
  {
    $query->condition('base_table.id', '-1');
    return $query;
  }

  /**
   * @inheritDoc
   */
  public function userHasAccess(Model $model, int $uid): bool
  {
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function shouldSetAccessPolicy(Model $model): bool
  {
    return FALSE;
  }

  /**
   * @inheritDoc
   */
  public function getUserAccessForModelClass(User $user, string $modelClass): array
  {
    return [];
  }

  /**
   * @inheritDoc
   */
  public function rebuildForModelClass(string $modelClass): AccessPolicyInterface
  {
    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getUserIdsWithAccess(string $entityTypeId, string $entityId): array
  {
    return [];
  }
}
