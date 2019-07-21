<?php

namespace Drupal\spectrum\Permissions;

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
   * @param integer $uid
   * @return void
   */
  public function removeUserFromAccessPolicy(int $uid): void;

  /**
   * Trigger this when the access policy of a user needs to be recalculated.
   * This will be automatically triggered after an insert, and update of a User
   * @param int $uid
   *
   * @throws \Exception
   */
  public function updateUserAccessPolicy(int $uid): void;

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
}
