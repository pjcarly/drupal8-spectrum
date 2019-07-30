<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This is the base implementation class for any ApiHandler, it exposes logic that makes it possible to return results from a RestRequest.
 * Requests will be routed by the REST method to the different functions on this class,
 * or to an action that can be defined per ApiHandler to execute custom functionality that doesnt fit in the traditional REST structure
 */
abstract class BaseApiHandler
{
  /**
   * The slug that was provided in the query string when calling the API
   *
   * @var string|int|null
   */
  protected $slug;

  /**
   * Default response headers that will be added to every response.
   *
   * @var array
   */
  protected $defaultHeaders = [];

  /**
   * @param string|int|null $slug The slug that was provided in the query string when calling the API
   */
  public function __construct($slug = NULL)
  {
    $this->slug = $slug;
  }

  /**
   * * Rest GET calls to this API handler will be handled here
   *
   * @param Request $request
   * @return Response
   */
  public function get(Request $request): Response
  {
    return new Response(null, 405, []);
  }

  /**
   * * Rest POST calls to this API handler will be handled here
   *
   * @param Request $request
   * @return Response
   */
  public function post(Request $request): Response
  {
    return new Response(null, 405, []);
  }

  /**
   * * Rest PUT calls to this API handler will be handled here
   *
   * @param Request $request
   * @return Response
   */
  public function put(Request $request): Response
  {
    return new Response(null, 405, []);
  }

  /**
   * * Rest PATCH calls to this API handler will be handled here
   *
   * @param Request $request
   * @return Response
   */
  public function patch(Request $request): Response
  {
    return new Response(null, 405, []);
  }

  /**
   * * Rest DELETE calls to this API handler will be handled here
   *
   * @param Request $request
   * @return Response
   */
  public function delete(Request $request): Response
  {
    return new Response(null, 405, []);
  }

  /**
   * * Rest OPTIONS calls to this API handler will be handled here
   *
   * @param Request $request
   * @return Response
   */
  public function options(Request $request): Response
  {
    return new Response(null, 405, []);
  }

  /**
   * Apply the default headers to the response
   *
   * @param Response $response
   * @return BaseApiHandler
   */
  private final function setDefaultHeaders(Response $response): BaseApiHandler
  {
    foreach (array_keys($this->defaultHeaders) as $key) {
      $response->headers->set($key, $this->defaultHeaders[$key]);
    }

    return $this;
  }

  /**
   * This method will split the Request based on the REST method or action to the correct function on the class
   * Default headers will be added to the response
   *
   * @param Request $request
   * @param string $action
   * @return Response
   */
  public final function handle(Request $request, string $action = NULL): Response
  {
    $response = null;
    $method = $request->getMethod();

    // strip all none alpha numeric characters, an action is a function name
    $action = empty($action) ? '' : preg_replace('/[^A-Za-z0-9 ]/', '', $action);

    if ($method === 'GET' && empty($action)) {
      $response = $this->get($request);
    } else if ($method === 'POST') {
      if (empty($action)) {
        // Normal post, lets handle it via the POST method
        $response = $this->post($request);
      } else {
        $action = ucfirst(strtolower($action));
        $method = 'action' . $action;

        if (is_callable([$this, $method])) {
          $response = $this->$method($request);
        }
      }
    } else if ($method === 'PUT' && empty($action)) {
      $response = $this->put($request);
    } else if ($method === 'PATCH' && empty($action)) {
      $response = $this->patch($request);
    } else if ($method === 'DELETE' && empty($action)) {
      $response = $this->delete($request);
    } else if ($method === 'OPTIONS' && empty($action)) {
      $response = $this->options($request);
    }

    if (empty($response)) {
      $response = new Response(null, 400, []);
    } else {
      $this->setDefaultHeaders($response);
    }

    return $response;
  }
}
