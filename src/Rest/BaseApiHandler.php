<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BaseApiHandler
{
  public static function handle(Request $request)
  {
    $method = $request->getMethod();

    if($method === 'GET' && method_exists(get_called_class(), 'get'))
    {
      return static::get($request);
    }
    else if($method === 'POST' && method_exists(get_called_class(), 'post'))
    {
      return static::post($request);
    }
    else if($method === 'PUT' && method_exists(get_called_class(), 'put'))
    {
      return static::put($request);
    }
    else if($method === 'OPTIONS' && method_exists(get_called_class(), 'options'))
    {
      return static::options($request);
    }
    else if($method === 'DELETE' && method_exists(get_called_class(), 'delete'))
    {
      return static::delete($request);
    }
    else
    {
      return new Response(null, 404, array());
    }
  }
}
