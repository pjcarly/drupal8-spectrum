<?php

namespace Drupal\spectrum\Permissions;

use Drupal\spectrum\Models\User;

/**
 * This interface exposes the different functions Spectrum needs to be able to provide proper access or security checks.
 * Everyone can choose to implement this how they want, as long as the service is registered in the container, and these functions are present
 */
interface PermissionServiceInterface
{
  /**
   * Trigger this when you want to remove a user from the access policies completely
   * This will be triggered automatically after a delete of a user.
   *
   * @param User $user
   * @return void
   */
  public function removeUserFromAccessPolicies(User $user): void;

  /**
   * Trigger this when the access policy of a user needs to be recalculated.
   * This will be automatically triggered after an insert, and update of a User
   * @param User $user
   *
   * @throws \Exception
   */
  public function rebuildAccessPoliciesForUser(User $user): void;

  /**
   * Rebuilds the access policy table.
   */
  public function rebuildAccessPolicy(): void;

  /**
   * Rebuilds the access policy for a specific entity
   *
   * @param string $entity
   * @return void
   */
  public function rebuildAccessPolicyForEntity(string $entity): void;

  /**
   * Rebuilds the access policy for a specific entity and bundle
   *
   * @param string $entity
   * @param string $bundle
   * @return void
   */
  public function rebuildAccessPolicyForEntityAndBundle(string $entity, string $bundle): void;

  /**
   * Rebuilds the access policy for a specific model class
   *
   * @param string $class
   * @return void
   */
  public function rebuildAccessPolicyForModelClass(string $class): void;

  /**
   * This function checks whether the given role has a certain access to the provided entity
   *
   * @param string $role The Drupal role of the User (for example, administrator, authenticated, anonymous, or any custom role)
   * @param string $permission The permission that will be checked in the form of "entity_bundle" (for example node_article)
   * @param string $access The access that will be checked C, R, U or D (Create, Read, Update or Delete)
   * @return boolean
   */
  public function roleHasModelPermission(string $role, string $permission, string $access): bool;

  /**
   * This function checks whether a Permission is defined for the API
   *
   * @param string $route The Symfony route of your BaseApiController (for example "spectrum.content")
   * @param string $api The API key in your controller that redirects to your BaseApiHandler (for example "nodes")
   * @return boolean
   */
  public function apiPermissionExists(string $route, string $api): bool;

  /**
   * This function checks whether an action Permission is defined for the API
   *
   * @param string $route The Symfony route of your BaseApiController (for example "spectrum.content")
   * @param string $api The API key in your controller that redirects to your BaseApiHandler (for example "nodes")
   * @return boolean
   */
  public function apiActionPermissionExists(string $route, string $api): bool;


  /**
   * Check whether the provided user role has the correct API Permission
   *
   * @param string $role The Drupal role of the User (for example, administrator, authenticated, anonymous, or any custom role)
   * @param string $route The Symfony route of your BaseApiController (for example "spectrum.content")
   * @param string $api The API key in your controller that redirects to your BaseApiHandler (for example "nodes")
   * @param string $access The access that will be checked C, R, U or D (Create, Read, Update or Delete)
   * @return boolean
   */
  public function roleHasApiPermission(string $role, string $route, string $api, string $access): bool;

  /**
   * This function checks whether an API is publicly accessible 
   * (does not require a login, and is thus accessible by the AnonymousUserSession)
   *
   * @param string $route
   * @param string $api
   * @return boolean
   */
  public function apiIsPubliclyAccessible(string $route, string $api): bool;

  /**
   * Check whether a Drupal Role, has a certain OAuth scope
   *
   * @param string $role
   * @param string $scope
   * @return boolean
   */
  public function roleHasOAuthScopePermission(string $role, string $scope): bool;

  /**
   * Check whether a Drupal Role, has access to an APIHandler Action function
   *
   * @param string $role
   * @param string $route
   * @param string $api
   * @param string $action
   * @return boolean
   */
  public function roleHasApiActionPermission(string $role, string $route, string $api, string $action): bool;

  /**
   * Check whether a provided Drupal Role has access to a certain field
   *
   * @param string $role
   * @param string $entity
   * @param string $field
   * @param string $access Can either be 'view' (when a user just wants to READ a field) or 'edit' (when a user wants to change the value of a field)
   * @return boolean
   */
  public function roleHasFieldPermission(string $role, string $entity, string $field, string $access): bool;
}
