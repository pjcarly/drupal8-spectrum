<?php

namespace Drupal\spectrum\Models;

use Drupal\spectrum\Model\Model;

class User extends Model
{
  /**
   * THe entityType of this Model
   *
   * @var string
   */
  public static $entityType = 'user';

  /**
   * The Bundle of this Model
   *
   * @var string
   */
  public static $bundle = '';

  /**
   * The IdField of this Model
   *
   * @var string
   */
  public static $idField = 'uid';

  /**
   * THe Plural description of this model
   *
   * @var string
   */
  public static $plural = 'Users';

  /**
   * This variable will hold a cache of the current user during this transaction
   *
   * @var [type]
   */
  public static $currentUser = null;

  /**
   * THe relationships to other models
   *
   * @return void
   */
  public static function relationships()
  {

  }

  /**
   * alias of loggedInUser()
   *
   * @return User
   */
  public static function currentUser() : User
  {
    return static::loggedInUser();
  }

  /**
   * Returns the logged in User build with the registered Model Class
   *
   * @return User
   */
  public static function loggedInUser() : User
  {
    // We cant just use static, because we want the User Model which can potentially be overridden in the Model Service
    // This must extend the current class, so we can be sure that the return type will be the current class
    $currentUser = static::$currentUser;

    if(empty($currentUser))
    {
      $userType = Model::getModelClassForEntityAndBundle(static::$entityType, static::$bundle);
      $currentUser = $userType::forgeById(\Drupal::currentUser()->id());
      static::$currentUser = $currentUser;
    }

    return $currentUser;
  }

  /**
   * Returns the roles of the user
   *
   * @return array
   */
  public function getRoles() : array
  {
    return $this->entity->getRoles();
  }

  /**
   * Check if the user is an Anonymous user
   *
   * @return boolean
   */
  public function isAnonymous() : bool
  {
    return $this->entity->isAnonymous();
  }

  /**
   * Check if a role exist on the User
   *
   * @param string $role
   * @return boolean
   */
  public function hasRole(string $role) : bool
  {
    $roles = $this->getRoles();

    return in_array($role, $roles);
  }

  /**
   * Check if the user is active
   *
   * @return boolean
   */
  public function isActive() : bool
  {
    return $this->entity->isActive();
  }

  /**
   * Activate the user (in memory, model must be saved to persist)
   *
   * @return User
   */
  public function activate() : User
  {
    $this->entity->status->value = true;
    return $this;
  }

  /**
   * Block the user (in memory, model must be saved to persist)
   *
   * @return User
   */
  public function block() : User
  {
    $this->entity->status->value = false;
    return $this;
  }

  /**
   * Checks whether the user has access to a certain field on a model
   *
   * @param string $modelClass This is a fully qualified Class name of the model (for Example Drupal\spectrum\Models\User)
   * @param string $field The field on the model (for example "field_body")
   * @param string $access What type of access ("view" or "edit")
   * @return boolean
   */
  public function hasFieldPermission(string $modelClass, string $field, string $access) : bool
  {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = false;

    $entity = $modelClass::getBasePermissionKey();
    foreach($this->getRoles() as $userRole)
    {
      if($permissionService->roleHasFieldPermission($userRole, $entity, $field, $access))
      {
        $permissionGranted = true;
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
   * @return boolean
   */
  public function hasApiActionPermission(string $route, string $api, string $action) : bool
  {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = false;

    foreach($this->getRoles() as $userRole)
    {
      if($permissionService->roleHasApiActionPermission($userRole, $route, $api, $action))
      {
        $permissionGranted = true;
        break;
      }
    }

    return $permissionGranted;
  }

    /**
   * Checks whether the logged in user has access to the provided API
   *
   * @param string $route The Symfony route, that points to your BaseApiController implementation (for example "spectrum.content")
   * @param string $api The api string in your ApiController that matches your ApiHandler (for example "nodes")
   * @param string $access C, R, U or D
   * @return boolean
   */
  public function hasApiPermission(string $route, string $api, string $access) : bool
  {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = false;

    foreach($this->getRoles() as $userRole)
    {
      if($permissionService->roleHasApiPermission($userRole, $route, $api, $access))
      {
        $permissionGranted = true;
        break;
      }
    }

    return $permissionGranted;
  }

  /**
   * Check if the user has permission for a model
   *
   * @param string $modelClass This is a fully qualified Class name of the model (for Example Drupal\spectrum\Models\User)
   * @param string $access (either C, R, U or D)
   * @return boolean
   */
  public function hasModelPermission(string $modelClass, string $access) : bool
  {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = false;
    $modelPermissionKey = $modelClass::getBasePermissionKey();

    foreach($this->getRoles() as $userRole)
    {
      if($permissionService->roleHasModelPermission($userRole, $modelPermissionKey, $access))
      {
        $permissionGranted = true;
        break;
      }
    }

    return $permissionGranted;
  }

    /**
   * Checks the permission service for the oauth scope permission of the current user
   *
   * @param string $scope
   * @return boolean
   */
  public function hasOAuthScopePermission(string $scope) : bool
  {
    $permissionService = Model::getPermissionsService();
    $permissionGranted = false;

    foreach($this->getRoles() as $role)
    {
      if($permissionService->roleHasOAuthScopePermission($role, $scope))
      {
        $permissionGranted = true;
        break;
      }
    }

    return $permissionGranted;
  }
}
