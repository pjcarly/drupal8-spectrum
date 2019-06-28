<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\Select;
use Drupal\spectrum\Model\Model;

/**
 * Class PublicAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessPolicy
 */
class PublicAccessPolicy implements AccessPolicyInterface {

  /**
   * @inheritDoc
   */
  public function onSave(Model $model): void {
    $entityType = $model::entityType();
    $entityId = $model->getId();

    $database = \Drupal::database();

    // Delete all current permissions.
    $database->delete(self::TABLE_ENTITY_ACCESS)
      ->condition('entity_type', $entityType)
      ->condition('entity_id', $entityId)
      ->execute();

    // Insert permissions.
    $insertQuery = $database->insert(self::TABLE_ENTITY_ACCESS);
    $insertQuery->fields([
      'entity_type',
      'entity_id',
      'uid',
    ]);
    $insertQuery->values([
      'entity_type' => $entityType,
      'entity_id' => $entityId,
      // We use UID 0 for public access.
      'uid' => 0,
    ]);
    $insertQuery->execute();

    // Set the root model for all children.
    (new ParentAccessPolicy)->onSave($model);
  }

  /**
   * @inheritDoc
   */
  public function onQuery(Select $query): Select {
    return $query;
  }

}
