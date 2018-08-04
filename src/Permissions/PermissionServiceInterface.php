<?php

namespace Drupal\spectrum\Permissions;

interface PermissionServiceInterface
{
  /**
   * This function checks whether the given role has a certain access to the provided entity
   *
   * @param string $role The Drupal role of the User (for example, administrator, authenticated, anonymous, or any custom role)
   * @param string $permission The permission that will be checked in the form of "entity_bundle" (for example node_article)
   * @param string $access The access that will be checked C, R, U or D (Create, Read, Update or Delete)
   * @return boolean
   */
  public function roleHasModelPermission(string $role, string $permission, string $access) : bool;

  /**
   * This function checks whether a Permission is defined for the API
   *
   * @param string $route The Symfony route of your BaseApiController (for example "spectrum.content")
   * @param string $api The API key in your controller that redirects to your BaseApiHandler (for example "nodes")
   * @return boolean
   */
  public function apiPermissionExists(string $route, string $api) : bool;

  /**
   * This function checks whether an action Permission is defined for the API
   *
   * @param string $route The Symfony route of your BaseApiController (for example "spectrum.content")
   * @param string $api The API key in your controller that redirects to your BaseApiHandler (for example "nodes")
   * @return boolean
   */
  public function apiActionPermissionExists(string $route, string $api) : bool;


  /**
   * Check whether the provided user role has the correct API Permission
   *
   * @param string $role The Drupal role of the User (for example, administrator, authenticated, anonymous, or any custom role)
   * @param string $route The Symfony route of your BaseApiController (for example "spectrum.content")
   * @param string $api The API key in your controller that redirects to your BaseApiHandler (for example "nodes")
   * @param string $access The access that will be checked C, R, U or D (Create, Read, Update or Delete)
   * @return boolean
   */
  public function roleHasApiPermission(string $role, string $route, string $api, string $access) : bool;
}
