<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Models\User;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

abstract class AccessPolicyBase implements AccessPolicyInterface, LoggerAwareInterface
{

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * PublicAccessPolicy constructor.
   */
  public function __construct()
  {
    $this->database = \Drupal::database();
    $this->logger = \Drupal::logger('spectrum');
  }

  /**
   * @inheritDoc
   */
  public function setLogger(LoggerInterface $logger)
  {
    $this->logger = $logger;
  }

  /**
   * @param \Drupal\spectrum\Model\Model $model
   */
  protected function removeAccess(Model $model): AccessPolicyInterface
  {
    $this->database->delete(AccessPolicyInterface::TABLE_ENTITY_ACCESS)
      ->condition('entity_type', $model::entityType())
      ->condition('entity_id', (int) $model->getId())
      ->execute();

    return $this;
  }

  /**
   * @inheritDoc
   */
  public function rebuildForModelClass(string $modelClass): AccessPolicyInterface
  {
    $message = strtr('Rebuilding access policy for "@model" @usage.', [
      '@model' => $modelClass,
      '@usage' => memory_get_usage() / 1024,
    ]);
    $this->logger->info($message);

    /** @var Model $modelClass */
    $query = $modelClass::getModelQuery();

    /** @var Model $model */
    foreach ($query->fetchGenerator() as $model) {
      $cache = \Drupal::service('entity.memory_cache');
      $cache->deleteAll();
      $accessPolicy = $model::getAccessPolicy();
      $accessPolicy->onSave($model);
    }

    return $this;
  }

  /**
   * @inheritDoc
   */
  public function getUserAccessForModelClass(User $user, string $modelClass): array
  {
    return [];
  }


  /**
   * @param \Drupal\spectrum\Model\Model $model
   *
   * @return array
   */
  public function getRootsForModel(Model $model): array
  {
    return [$model];
  }
}
