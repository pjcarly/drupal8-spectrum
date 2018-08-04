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
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Models\User;

class BaseApiController implements ContainerAwareInterface
{
  use ContainerAwareTrait;

  /**
   * This function adds the CORS headers to the Response
   *
   * @param RouteMatchInterface $routeMatch
   * @param Request $request
   * @param Response $response
   * @return void
   */
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

  /**
   * Returns string representing the Access for the Permission check based on the REST Method
   *
   * @param Request $request
   * @return string C, R, U or D (C = POST, R = GET,  U = PATCH, D = DELETE)
   */
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

  public function handle(RouteMatchInterface $routeMatch, Request $request, $api = NULL, $slug = NULL, $action = NULL)
  {
    $response = new Response(null, 500, array());
    $permissionService = Model::getPermissionsService();

    // Permissions are different with or without an action
    if(empty($action))
    {
      if($permissionService->apiPermissionExists($routeMatch->getRouteName(), $api))
      {
        $user = User::loggedInUser();
        $access = $this->getAccessForRequestMethod($request);
        if($user->hasApiPermission($routeMatch->getRouteName(), $api, $access))
        {
          $response->setStatusCode(204); // OK, no content
          $response->setContent(null);
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
    }
    else
    {
      // The request is to an Action
      if($permissionService->apiActionPermissionExists($routeMatch->getRouteName(), $api))
      {
        $user = User::loggedInUser();
        if($user->hasApiActionPermission($routeMatch->getRouteName(), $api, $action))
        {
          $response->setStatusCode(204); // OK, no content
          $response->setContent(null);
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
    }

    $this->addCorsHeaders($routeMatch, $request, $response);

    return $response;
  }
}
