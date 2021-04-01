<?php

namespace Drupal\spectrum\Models;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Permissions\AccessPolicy\PublicAccessPolicy;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User as DrupalUser;

/**
 * Class User
 *
 * @package Drupal\spectrum\Models
 */
class User extends Model
{

  /**
   * This variable will hold a cache of the current user during this transaction
   *
   * @var [type]
   */
  public static $currentUser = NULL;

  /**
   * The Entitytype of this model
   *
   * @return string
   */
  public static function entityType(): string
  {
    return 'user';
  }

  /**
   * The Bundle of this model
   *
   * @return string
   */
  public static function bundle(): string
  {
    return '';
  }

  /**
   * The relationships to other models.
   *
   * @return void
   */
  public static function relationships()
  {
  }

  /**
   * @inheritDoc
   */
  public function afterInsert()
  {
    parent::afterInsert();
    Model::getPermissionsService()->rebuildAccessPoliciesForUser($this);
  }

  /**
   * @inheritDoc
   */
  public function afterUpdate()
  {
    parent::afterUpdate();
    Model::getPermissionsService()->rebuildAccessPoliciesForUser($this);
  }

  /**
   * @inheritDoc
   */
  public function beforeDelete()
  {
    parent::beforeDelete();
    Model::getPermissionsService()->removeUserFromAccessPolicies($this);
  }

  /**
   * @inheritDoc
   */
  public static function getAccessPolicy(): AccessPolicyInterface
  {
    return new PublicAccessPolicy;
  }

  /**
   * @return \Drupal\spectrum\Models\User
   * @throws \Drupal\spectrum\Exceptions\ModelClassNotDefinedException
   */
  public static function currentUser(): User
  {
    return static::loggedInUser();
  }

  /**
   * Returns the logged in User build with the registered Model Class
   *
   * @return \Drupal\spectrum\Models\User
   * @throws \Drupal\spectrum\Exceptions\ModelClassNotDefinedException
   */
  public static function loggedInUser(): User
  {
    // We cant just use static, because we want the User Model which can
    // potentially be overridden in the Model Service.
    // This must extend the current class, so we can be sure that the return
    // type will be the current class.
    $currentUser = static::$currentUser;

    if (empty($currentUser)) {
      $userType = Model::getModelClassForEntityAndBundle(
        static::entityType(),
        static::bundle()
      );
      $currentUser = $userType::forgeById(\Drupal::currentUser()->id());
      static::$currentUser = $currentUser;
    }

    return $currentUser;
  }

  /**
   * Returns the roles of the user
   *
   * @return string[]
   */
  public function getRoles(): array
  {
    /** @var DrupalUser $entity */
    $entity = $this->entity;
    return $entity->getRoles();
  }

  /**
   * Check if the user is an Anonymous user
   *
   * @return boolean
   */
  public function isAnonymous(): bool
  {
    /** @var DrupalUser $entity */
    $entity = $this->entity;
    return $entity->isAnonymous();
  }

  /**
   * Check if a role exist on the User
   *
   * @param string $role
   * @return boolean
   */
  public function hasRole(string $role): bool
  {
    $roles = $this->getRoles();
    return in_array($role, $roles);
  }

  /**
   * Gives a Role to a User
   *
   * @param Role $role
   * @return User
   */
  public function addRole(Role $role): User
  {
    /** @var DrupalUser $entity */
    $entity = $this->entity;
    $entity->addRole($role->id());
    return $this;
  }

  /**
   * Check if the user is active
   *
   * @return boolean
   */
  public function isActive(): bool
  {
    /** @var DrupalUser $entity */
    $entity = $this->entity;
    return $entity->isActive();
  }

  /**
   * Returns true if the current user has the role Administrator
   *
   * @return boolean
   */
  public function isAdministrator(): bool
  {
    return $this->hasRole('administrator');
  }

  /**
   * Activate the user (in memory, model must be saved to persist)
   *
   * @return User
   */
  public function activate(): User
  {
    $this->entity->{'status'}->value = TRUE;
    return $this;
  }

  /**
   * Block the user (in memory, model must be saved to persist)
   *
   * @return User
   */
  public function block(): User
  {
    $this->entity->{'status'}->value = FALSE;
    return $this;
  }

  /**
   * Sets the Unhashed password on the User
   *
   * @param string $value
   * @return User
   */
  public function setPassword(string $value): User
  {
    /** @var DrupalUser $entity */
    $entity = $this->entity;

    $entity->setPassword($value);
    return $this;
  }

  /**
   * @param string $value
   *
   * @return User
   */
  public function setEmail(string $value): User
  {
    $this->entity->{'mail'}->value = $value;
    return $this;
  }

  /**
   * @return string
   */
  public function getEmail(): string
  {
    return $this->entity->{'mail'}->value;
  }

  /**
   * @param string $value
   *
   * @return User
   */
  public function setName(string $value): User
  {
    $this->entity->{'name'}->value = $value;
    return $this;
  }

  /**
   * @return string
   */
  public function getUsername(): string
  {
    if ($this->getId() === 0) {
      return 'Anonymous';
    }

    return $this->entity->{'name'}->value;
  }

  /**
   * Checks whether the user has access to a certain field on a model.
   *
   * @param string $modelClass This is a fully qualified Class name of the model (for Example Drupal\spectrum\Models\User)
   * @param string $field The field on the model (for example "field_body")
   * @param string $access What type of access ("view" or "edit")
   *
   * @return bool
   * @throws \Drupal\spectrum\Exceptions\NotImplementedException
   */
  public function hasFieldPermission(
    string $modelClass,
    string $field,
    string $access
  ): bool {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = FALSE;

    $entity = $modelClass::getBasePermissionKey();
    foreach ($this->getRoles() as $userRole) {
      if ($permissionService->roleHasFieldPermission($userRole, $entity, $field, $access)) {
        $permissionGranted = TRUE;
        break;
      }
    }

    return $permissionGranted;
  }

  /**
   * Checks whether the logged in user has access to the Action defined on an API Handler
   *
   * @param string $route The Symfony route, that points to your BaseApiController implementation (for example "spectrum.content")
   * @param string $api The api string in your ApiController that matches your ApiHandler (for example "nodes")
   * @param string $action The action defined on the ApiHandler (for example "publish")
   *
   * @return bool
   * @throws \Drupal\spectrum\Exceptions\NotImplementedException
   */
  public function hasApiActionPermission(
    string $route,
    string $api,
    string $action
  ): bool {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = FALSE;

    foreach ($this->getRoles() as $userRole) {
      if ($permissionService->roleHasApiActionPermission($userRole, $route, $api, $action)) {
        $permissionGranted = TRUE;
        break;
      }
    }

    return $permissionGranted;
  }

  /**
   * Checks whether the logged in user has access to the provided API.
   *
   * @param string $route The Symfony route, that points to your BaseApiController implementation (for example "spectrum.content")
   * @param string $api The api string in your ApiController that matches your ApiHandler (for example "nodes")
   * @param string $access C, R, U or D
   *
   * @return bool
   * @throws \Drupal\spectrum\Exceptions\NotImplementedException
   */
  public function hasApiPermission(
    string $route,
    string $api,
    string $access
  ): bool {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = FALSE;

    foreach ($this->getRoles() as $userRole) {
      if ($permissionService->roleHasApiPermission($userRole, $route, $api, $access)) {
        $permissionGranted = TRUE;
        break;
      }
    }

    return $permissionGranted;
  }

  /**
   * Check if the user has permission for a model.
   *
   * @param string $modelClass This is a fully qualified Class name of the model
   * (for Example Drupal\spectrum\Models\User)
   * @param string $access (either C, R, U or D)
   *
   * @return bool
   * @throws \Drupal\spectrum\Exceptions\NotImplementedException
   */
  public function hasModelPermission(
    string $modelClass,
    string $access
  ): bool {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = FALSE;
    $modelPermissionKey = $modelClass::getBasePermissionKey();

    foreach ($this->getRoles() as $userRole) {
      if ($permissionService->roleHasModelPermission($userRole, $modelPermissionKey, $access)) {
        $permissionGranted = TRUE;
        break;
      }
    }

    return $permissionGranted;
  }

  /**
   * Checks the permission service for the oauth scope permission of the current
   * user.
   *
   * @param string $scope
   *
   * @return bool
   * @throws \Drupal\spectrum\Exceptions\NotImplementedException
   */
  public function hasOAuthScopePermission(string $scope): bool
  {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = FALSE;

    foreach ($this->getRoles() as $role) {
      if ($permissionService->roleHasOAuthScopePermission($role, $scope)) {
        $permissionGranted = TRUE;
        break;
      }
    }

    return $permissionGranted;
  }
}
