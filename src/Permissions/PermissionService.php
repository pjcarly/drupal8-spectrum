<?php

namespace Drupal\spectrum\Permissions;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Models\User;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyEntity;
use Drupal\spectrum\Permissions\PermissionServiceInterface;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Query\ConditionGroup;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Class PermissionService
 *
 * @package Drupal\spectrum\Services
 */
class PermissionService implements PermissionServiceInterface, LoggerAwareInterface
{

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * PermissionService constructor.
   */
  public function __construct()
  {
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
   * @param string $role
   * @param string $scope
   *
   * @return bool
   */
  public function roleHasOAuthScopePermission(string $role, string $scope): bool
  {
    return true;
  }

  /**
   * @param string $role
   * @param string $permission
   * @param string $access
   *
   * @return bool
   */
  public function roleHasModelPermission(string $role, string $permission, string $access): bool
  {
    return true;
  }

  /**
   * @param string $role
   * @param string $route
   * @param string $api
   * @param string $access
   *
   * @return bool
   */
  public function roleHasApiPermission(string $role, string $route, string $api, string $access): bool
  {
    return true;
  }

  /**
   * @param string $role
   * @param string $route
   * @param string $api
   * @param string $action
   *
   * @return bool
   */
  public function roleHasApiActionPermission(string $role, string $route, string $api, string $action): bool
  {
    return true;
  }

  /**
   * @param string $route
   * @param string $api
   *
   * @return bool
   */
  public function apiPermissionExists(string $route, string $api): bool
  {
    return true;
  }

  /**
   * @param string $route
   * @param string $api
   *
   * @return bool
   */
  public function apiActionPermissionExists(string $route, string $api): bool
  {
    return true;
  }

  /**
   * @param string $role
   * @param string $entity
   * @param string $field
   * @param string $access
   *
   * @return bool
   */
  public function roleHasFieldPermission(string $role, string $entity, string $field, string $access): bool
  {
    return true;
  }

  /**
   * Rebuilds the access policy table.
   * @todo move this upstream and use dependency injection to override this
   * class.
   */
  public function rebuildAccessPolicy(): void
  {
    $classes = Model::getModelService()->getRegisteredModelClasses();

    /** @var \Drupal\spectrum\Model\Model $class */
    foreach ($classes as $class) {
      $accessPolicy = $class::getAccessPolicy();

      /** @var string $class */
      $accessPolicy->rebuildForModelClass($class);
    }
  }

  /**
   * @param int $uid
   */
  public function removeUserFromAccessPolicies(User $user): void
  {
    \Drupal::database()
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

    $classes = Model::getModelService()->getRegisteredModelClasses();
    /** @var Model $class */
    foreach ($classes as $class) {
      $accessPolicy = $class::getAccessPolicy();

      /** @var string $class */
      /** @var AccessPolicyEntity[] $values */
      $values = array_merge($values, $accessPolicy->getUserAccessForModelClass($user, $class));
    }

    if (!empty($values)) {
      $insertQuery = \Drupal::database()
        ->insert(AccessPolicyInterface::TABLE_ENTITY_ACCESS)
        ->fields(['entity_type', 'entity_id', 'uid']);

      foreach ($values as $value) {
        $insertQuery = $insertQuery->values($value->getInsertValue());
      }

      $insertQuery->execute();
    }
  }
}
