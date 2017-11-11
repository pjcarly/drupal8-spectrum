<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\RouteMatchInterface;

use Drupal\Core\Path\PathMatcher;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Drupal\cors\EventSubscriber\CorsResponseEventSubscriber;


class BaseApiController implements ContainerAwareInterface
{
  use ContainerAwareTrait;

  protected function addCorsHeaders(RouteMatchInterface $routeMatch, Request $request, Response $response)
  {
    // We let the request pass through Drupal's internals, so other modules can apply correct headers
    $config = \Drupal::service('config.factory');
    $alias_manager = \Drupal::service('path.alias_manager');
    $path_matcher = new PathMatcher($config, $routeMatch);

    $kernel = \Drupal::service('http_kernel.basic');
    $event = new FilterResponseEvent($kernel, $request, null, $response);

    $cors = new CorsResponseEventSubscriber($config, $alias_manager, $path_matcher);
    $cors->addCorsHeaders($event);
  }

  // Returns string representing
  // C = POST
  // R = GET
  // U = PATCH
  // D = DELETE
  private function getAccessForRequestMethod(Request $request) : string
  {
    $access = '';
    switch($request->getMethod())
    {
      case 'GET':
        $access = 'R';
        break;
      case 'POST':
        $access = 'C';
        break;
      case 'PATCH':
        $access = 'U';
        break;
      case 'DELETE':
        $access = 'D';
        break;
    }

    return $access;
  }

  protected function apiPermissionExists(string $route, string $api)
  {
    $permissionExists = false;

    // Workaround because of custom permission bug in Drupal
    if(function_exists('get_permission_checker'))
    {
      $permissionChecker = get_permission_checker();
      $permissionExists = $permissionChecker::apiPermissionExists($route, $api);
    }
    else
    {
      $permissionExists = false;
    }

    return $permissionExists;
  }

  protected function userHasApiPermission(Request $request, string $route, string $api) : bool
  {
    $access = $this->getAccessForRequestMethod($request);

    $currentUser = \Drupal::currentUser();
    $permissionGranted = false;

    // Workaround because of custom permission bug in Drupal
    if(function_exists('get_permission_checker'))
    {
      $permissionChecker = get_permission_checker();

      $userRoles = $currentUser->getRoles();
      foreach($userRoles as $userRole)
      {
        if($permissionChecker::roleHasApiPermission($userRole, $route, $api, $access))
        {
          $permissionGranted = true;
          break;
        }
      }
    }
    else
    {
      $permissionGranted = false;
    }

    return $permissionGranted;
  }

  public function handle(RouteMatchInterface $routeMatch, Request $request, $api = NULL, $slug = NULL, $action = NULL)
  {
    $response = new Response(null, 500, array());

    if($this->apiPermissionExists($routeMatch->getRouteName(), $api))
    {
      if($this->userHasApiPermission($request, $routeMatch->getRouteName(), $api))
      {
        $response->setStatusCode(204); // OK, no content
        $response->setContent = null;
      }
      else
      {
        $response->setStatusCode(423); // Failed Locked
        $response->setContent(null);
      }
    }
    else
    {
      $response->setStatusCode(404); // Failed not found
      $response->setContent(null);
    }

    $this->addCorsHeaders($routeMatch, $request, $response);

    return $response;
  }
}
