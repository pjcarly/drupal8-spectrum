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

  protected $response;

  public function __construct()
  {
    $this->response = new Response(null, 404, array());
  }

  public function handle(RouteMatchInterface $route_match, Request $request, $api = NULL, $slug = NULL)
  {
    $config = \Drupal::service('config.factory');
    $alias_manager = \Drupal::service('path.alias_manager');
    $path_matcher = new PathMatcher($config, $route_match);

    $kernel = \Drupal::service('http_kernel.basic');
    $event = new FilterResponseEvent($kernel, $request, null, $this->response);

    $cors = new CorsResponseEventSubscriber($config, $alias_manager, $path_matcher);
    $cors->addCorsHeaders($event);

    return $this->response;
  }
}
