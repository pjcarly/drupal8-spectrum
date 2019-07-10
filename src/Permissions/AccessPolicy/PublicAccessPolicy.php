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
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * PublicAccessPolicy constructor.
   */
  public function __construct() {
    $this->database = \Drupal::database();
  }

  /**
   * @inheritDoc
   */
  public function onSave(Model $model): void {
    // Insert permissions.
    $insertQuery = $this->database->insert(self::TABLE_ENTITY_ACCESS);
    $insertQuery->fields([
      'entity_type',
      'entity_id',
      'uid',
    ]);
    $insertQuery->values([
      'entity_type' => $model::entityType(),
      'entity_id' => $model->getId(),
      // We use UID 0 for public access.
      'uid' => 0,
    ]);

    // Delete all current permissions.
    $this->removeAccess($model);

    $insertQuery->execute();

    // Set the root model for all children.
    (new ParentAccessPolicy)->onSave($model);
  }

  /**
   * @inheritDoc
   */
  public function onDelete(Model $model): void {
    $this->removeAccess($model);
    (new ParentAccessPolicy())->onDelete($model);
  }

  /**
   * @param \Drupal\spectrum\Model\Model $model
   */
  protected function removeAccess(Model $model): void {
    $this->database->delete(self::TABLE_ENTITY_ACCESS)
      ->condition('entity_type', $model::entityType())
      ->condition('entity_id', $model->getId())
      ->execute();
  }

  /**
   * @inheritDoc
   */
  public function onQuery(Select $query): Select {
    return $query;
  }

}
