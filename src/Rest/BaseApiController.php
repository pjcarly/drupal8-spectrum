<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\RouteMatchInterface;

use Drupal\Core\Path\PathMatcher;
use Drupal\cors\EventSubscriber\CorsResponseEventSubscriber;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Models\User;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The base API Controller to create custom webservice functionality. This class exposes a static handle() method which can be used in the Symfony routing files
 * This should be the entry point for all calls, and you should route the calls to ApiHandlers
 */
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
    $alias_manager = \Drupal::service('path_alias.manager');
    $path_matcher = new PathMatcher($config, $routeMatch);

    $kernel = \Drupal::service('http_kernel.basic');
    $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response);

    $cors = new CorsResponseEventSubscriber($config, $alias_manager, $path_matcher);
    $cors->addCorsHeaders($event);
  }

  /**
   * Returns string representing the Access for the Permission check based on the REST Method
   *
   * @param Request $request
   * @return string C, R, U or D (C = POST, R = GET,  U = PATCH, D = DELETE)
   */
  private function getAccessForRequestMethod(Request $request): string
  {
    $access = '';
    switch ($request->getMethod()) {
      case 'GET':
      case 'HEAD':
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

  /**
   * The default entry point for the Symfony routing, from here all calls should be routed to apihandlers.
   * CORS headers will be applied to responses, and Permission checking will be done via the PermissionService
   *
   * @param RouteMatchInterface $routeMatch
   * @param Request $request
   * @param string $api The API you want to reach, this is used to route the call to an API Handler
   * @param string $slug The slug that will be given to the API handler (for example the ID of a record you want to query)
   * @param string $action An optional Action you want to execute on the APIHandler (for example publish)
   * @return Response
   */
  public function handle(RouteMatchInterface $routeMatch, Request $request, string $api = NULL, string $slug = NULL, string $action = NULL): Response
  {
    $response = new Response(null, 500, []);
    $permissionService = Model::getPermissionsService();

    // Permissions are different with or without an action
    if (empty($action)) {
      if ($permissionService->apiPermissionExists($routeMatch->getRouteName(), $api)) {
        $user = User::loggedInUser();
        $access = $this->getAccessForRequestMethod($request);
        if ($user->hasApiPermission($routeMatch->getRouteName(), $api, $access)) {
          $response->setStatusCode(204); // OK, no content
          $response->setContent(null);
        } else {
          $response->setStatusCode(423); // Failed Locked
          $response->setContent(null);
        }
      } else {
        $response->setStatusCode(404); // Failed not found
        $response->setContent(null);
      }
    } else {
      // The request is to an Action
      if ($permissionService->apiActionPermissionExists($routeMatch->getRouteName(), $api)) {
        $user = User::loggedInUser();
        if ($user->hasApiActionPermission($routeMatch->getRouteName(), $api, $action)) {
          $response->setStatusCode(204); // OK, no content
          $response->setContent(null);
        } else {
          $response->setStatusCode(423); // Failed Locked
          $response->setContent(null);
        }
      } else {
        $response->setStatusCode(404); // Failed not found
        $response->setContent(null);
      }
    }

    $this->addCorsHeaders($routeMatch, $request, $response);

    return $response;
  }
}
