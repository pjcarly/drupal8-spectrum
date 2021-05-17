<?php

namespace Drupal\spectrum\Permissions;

use Drupal\Core\Database\Connection;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\ModelInterface;
use Drupal\spectrum\Model\ModelServiceInterface;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Models\User;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyEntity;
use Drupal\spectrum\Permissions\PermissionServiceInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Class PermissionService
 *
 * @package Drupal\spectrum\Services
 */
abstract class PermissionService implements PermissionServiceInterface, LoggerAwareInterface
{
  protected LoggerInterface $logger;
  protected ModelServiceInterface $modelService;
  protected Connection $database;

  /**
   * PermissionService constructor.
   */
  public function __construct(LoggerInterface $logger, ModelServiceInterface $modelService, Connection $database)
  {
    $this->logger = $logger;
    $this->modelService = $modelService;
    $this->database = $database;
  }

  /**
   * @inheritDoc
   */
  public function setLogger(LoggerInterface $logger)
  {
    $this->logger = $logger;
  }

  /**
   * Rebuilds the access policy table.
   * @todo move this upstream and use dependency injection to override this
   * class.
   */
  public function rebuildAccessPolicy(): void
  {
    $classes = $this->modelService->getRegisteredModelClasses();

    foreach ($classes as $class) {
      $this->rebuildAccessPolicyForModelClass($class);
    }
  }

  /**
   * Rebuilds the access policy for a specific entity
   *
   * @param string $entity
   * @return void
   */
  public function rebuildAccessPolicyForEntity(string $entity): void
  {
    $classes = $this->modelService->getRegisteredModelClasses();

    /** @var ModelInterface $class */
    foreach ($classes as $class) {
      if ($class::entityType() === $entity) {
        /** @var string $class */
        $this->rebuildAccessPolicyForModelClass($class);
      }
    }
  }

  /**
   * Rebuilds the access policy for a specific entity and bundle
   *
   * @param string $entity
   * @param string $bundle
   * @return void
   */
  public function rebuildAccessPolicyForEntityAndBundle(string $entity, string $bundle): void
  {
    $classes = $this->modelService->getRegisteredModelClasses();

    /** @var ModelInterface $class */
    foreach ($classes as $class) {
      if ($class::entityType() === $entity && $class::bundle() === $bundle) {
        /** @var string $class */
        $this->rebuildAccessPolicyForModelClass($class);
      }
    }
  }

  /**
   * Rebuilds the access policy for a specific model class
   *
   * @param string|Model $class
   * @return void
   */
  public function rebuildAccessPolicyForModelClass(string $class): void
  {
    /** @var ModelInterface $class */
    $accessPolicy = $class::getAccessPolicy();

    /** @var string $class */
    $accessPolicy->rebuildForModelClass($class);
  }

  /**
   * @param int $uid
   */
  public function removeUserFromAccessPolicies(User $user): void
  {
    $this->database
      ->delete(AccessPolicyInterface::TABLE_ENTITY_ACCESS)
      ->condition('uid', $user->getId())
      ->execute();
  }

  /**
   * @param int $uid
   *
   * @throws \Exception
   */
  public function rebuildAccessPoliciesForUser(User $user): void
  {
    $this->removeUserFromAccessPolicies($user);

    /** @var AccessPolicyEntity[] $values */
    $values = [];

    $classes = $this->modelService->getRegisteredModelClasses();
    /** @var Model $class */
    foreach ($classes as $class) {
      $accessPolicy = $class::getAccessPolicy();

      /** @var string $class */
      /** @var AccessPolicyEntity[] $values */
      $values = array_merge($values, $accessPolicy->getUserAccessForModelClass($user, $class));
    }

    if (!empty($values)) {
      $insertQuery = $this->database
        ->insert(AccessPolicyInterface::TABLE_ENTITY_ACCESS)
        ->fields(['entity_type', 'entity_id', 'uid']);

      $chunks = array_chunk($values, 10000);
      foreach ($chunks as $chunk) {
        foreach ($chunk as $value) {
          $insertQuery = $insertQuery->values($value->getInsertValue());
        }
      }

      $insertQuery->execute();
    }
  }
}
