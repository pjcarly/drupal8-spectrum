<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseApiHandler
{
  protected $slug;
  public function __construct($slug = NULL)
  {
    $this->slug = $slug;
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
    else if($method === 'OPTIONS')
    {
      $response = $this->options($request);
    }
    else if($method === 'DELETE')
    {
      $response = $this->delete($request);
    }
    else
    {
      $response = new Response(null, 404, array());
    }

    return $response;
  }
}
