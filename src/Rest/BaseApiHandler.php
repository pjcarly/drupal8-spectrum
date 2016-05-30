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
    $this->defaultHeaders = array();
  }

  public function get(Request $request)
  {
    return new Response(null, 404, array());
  }

  public function post(Request $request)
  {
    return new Response(null, 404, array());
  }

  public function put(Request $request)
  {
    return new Response(null, 404, array());
  }

  public function delete(Request $request)
  {
    return new Response(null, 404, array());
  }

  public function options(Request $request)
  {
    return new Response(null, 404, array());
  }

  private final function setDefaultHeaders(Response $response)
  {
    foreach(array_keys($this->defaultHeaders) as $key)
    {
      $response->headers->set($key, $this->defaultHeaders[$key]);
    }
  }

  public final function handle(Request $request)
  {
    $response;
    $method = $request->getMethod();

    if($method === 'GET')
    {
      $response = $this->get($request);
    }
    else if($method === 'POST')
    {
      $response = $this->post($request);
    }
    else if($method === 'PUT')
    {
      $response = $this->put($request);
    }
    else if($method === 'DELETE')
    {
      $response = $this->delete($request);
    }
    else if($method === 'OPTIONS')
    {
      $response = $this->options($request);
    }

    if(empty($response))
    {
      $response = new Response(null, 400, array());
    }
    else
    {
      $this->setDefaultHeaders($response);
    }

    return $response;
  }
}
