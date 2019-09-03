<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\Select;
use Drupal\spectrum\Model\Model;

/**
 * Class PublicAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessPolicy
 */
class PublicAccessPolicy extends AccessPolicyBase
{
  /**
   * @inheritDoc
   */
  public function onSave(Model $model): AccessPolicyInterface
  {
    $insertQuery = $this->database->insert(self::TABLE_ENTITY_ACCESS)
      ->fields([
        'entity_type',
        'entity_id',
        'uid',
      ])
      ->values([
        'entity_type' => $model::entityType(),
        'entity_id' => (int) $model->getId(),
        // We use UID 0 for public access.
        'uid' => 0,
      ]);

    // Delete all current permissions.
    $this->removeAccess($model);

    // Insert permissions.
    $insertQuery->execute();

    // Set the root model for all children.
    (new ParentAccessPolicy)->onSave($model);

    return $this;
  }

  /**
   * @inheritDoc
   */
  public function onDelete(Model $model): AccessPolicyInterface
  {
    $this->removeAccess($model);
    (new ParentAccessPolicy())->onDelete($model);

    return $this;
  }

  /**
   * @inheritDoc
   */
  public function onQuery(Select $query): Select
  {
    return $query;
  }

  /**
   * @inheritDoc
   */
  public function userHasAccess(Model $model, int $uid): bool
  {
    return TRUE;
  }

  /**
   * @inheritDoc
   */
  public function shouldSetAccessPolicy(Model $model): bool
  {
    return $model->isNew();
  }
}
