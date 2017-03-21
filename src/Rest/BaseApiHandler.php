<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseApiHandler
{
  protected $slug;
  protected $defaultHeaders;

  public function __construct($slug = NULL)
  {
    $this->slug = $slug;
    $this->defaultHeaders = [];
  }

  public function get(Request $request)
  {
    return new Response(null, 405, []);
  }

  public function post(Request $request)
  {
    return new Response(null, 405, []);
  }

  public function put(Request $request)
  {
    return new Response(null, 405, []);
  }

  public function patch(Request $request)
  {
    return new Response(null, 405, []);
  }

  public function delete(Request $request)
  {
    return new Response(null, 405, []);
  }

  public function options(Request $request)
  {
    return new Response(null, 405, []);
  }

  private final function setDefaultHeaders(Response $response)
  {
    foreach(array_keys($this->defaultHeaders) as $key)
    {
      $response->headers->set($key, $this->defaultHeaders[$key]);
    }
  }

  public final function handle(Request $request, $action = NULL)
  {
    $response;
    $method = $request->getMethod();

    // strip all none alpha numeric characters, an action is a function name
    $action = empty($action) ? '' : preg_replace('/[^A-Za-z0-9 ]/', '', $action);

    if($method === 'GET' && empty($action))
    {
      $response = $this->get($request);
    }
    else if($method === 'POST')
    {
      if(empty($action))
      {
        // Normal post, lets handle it via the POST method
        $response = $this->post($request);
      }
      else
      {
        $action = ucfirst(strtolower($action));
        $method = 'action'.$action;

        if(is_callable([$this, $method]))
        {
          $response = $this->$method($request);
        }
      }
    }
    else if($method === 'PUT' && empty($action))
    {
      $response = $this->put($request);
    }
    else if($method === 'PATCH' && empty($action))
    {
      $response = $this->patch($request);
    }
    else if($method === 'DELETE' && empty($action))
    {
      $response = $this->delete($request);
    }
    else if($method === 'OPTIONS' && empty($action))
    {
      $response = $this->options($request);
    }

    if(empty($response))
    {
      $response = new Response(null, 400, []);
    }
    else
    {
      $this->setDefaultHeaders($response);
    }

    return $response;
  }
}
