<?php

namespace Drupal\spectrum\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\ModelInterface;
use Drupal\spectrum\Services\ModelServiceInterface;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Models\User;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyEntity;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Class PermissionService
 *
 * @package Drupal\spectrum\Services
 */
class PermissionService implements PermissionServiceInterface, LoggerAwareInterface
{
  protected LoggerInterface $logger;
  protected ModelServiceInterface $modelService;
  protected Connection $database;
  protected AccountProxyInterface $currentUser;

  /**
   * PermissionService constructor.
   */
  public function __construct(
    LoggerInterface $logger,
    ModelServiceInterface $modelService,
    Connection $database,
    AccountProxyInterface $currentUser
  ) {
    $this->logger = $logger;
    $this->modelService = $modelService;
    $this->database = $database;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public function roleHasOAuthScopePermission(string $role, string $scope): bool
  {
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function roleHasModelPermission(string $role, string $permission, string $access): bool
  {
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function roleHasApiPermission(string $role, string $route, string $api, string $access): bool
  {
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function roleHasApiActionPermission(string $role, string $route, string $api, string $action): bool
  {
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function apiPermissionExists(string $route, string $api): bool
  {
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function apiIsPubliclyAccessible(string $route, string $api, ?string $action): bool
  {
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function apiActionPermissionExists(string $route, string $api): bool
  {
    return false;
  }

  /**
   * {@inheritdoc}
   */
  public function roleHasFieldPermission(string $role, string $entity, string $field, string $access): bool
  {
    return false;
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

  /**
   * Returns the base permission key in the form of "entity_bundle" (for example node_article) this is used for the permission checker
   *
   * @param string $modelClass
   * @return string
   */
  public function getPermissionKeyForModelClass(string $modelClass): string
  {
    return str_replace('.', '_', $this->modelService->getModelClassKey($modelClass));
  }

  /**
   * {@inheritdoc}
   */
  public function userHasFieldPermission(User $user, string $modelClass, string $field, string $access): bool
  {
    $permissionKey = $this->getPermissionKeyForModelClass($modelClass);

    $allowed = false;
    foreach ($user->getRoles() as $role) {
      if ($this->roleHasFieldPermission($role, $permissionKey, $field, $access)) {
        $allowed = true;
        break;
      }
    }

    return $allowed;
  }

  /**
   * {@inheritdoc}
   */
  public function userHasFieldViewPermission(User $user, string $modelClass, string $field): bool
  {
    return $this->userHasFieldPermission($user, $modelClass, $field, 'view');
  }

  /**
   * {@inheritdoc}
   */
  public function userHasFieldEditPermission(User $user, string $modelClass, string $field): bool
  {
    return $this->userHasFieldPermission($user, $modelClass, $field, 'edit');
  }

  /**
   * {@inheritdoc}
   */
  public function currentUserHasFieldPermission(string $modelClass, string $field, string $access): bool
  {
    $currentUser = User::forgeById($this->currentUser->id());
    return $this->userHasFieldPermission($currentUser, $modelClass, $field, $access);
  }

  /**
   * {@inheritdoc}
   */
  public function currentUserHasFieldViewPermission(string $modelClass, string $field): bool
  {
    $currentUser = User::forgeById($this->currentUser->id());
    return $this->userHasFieldPermission($currentUser, $modelClass, $field, 'view');
  }

  /**
   * {@inheritdoc}
   */
  public function currentUserHasFieldEditPermission(string $modelClass, string $field): bool
  {
    $currentUser = User::forgeById($this->currentUser->id());
    return $this->userHasFieldPermission($currentUser, $modelClass, $field, 'edit');
  }
}
