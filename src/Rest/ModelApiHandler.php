<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Drupal\spectrum\Rest\BaseApiHandler;
use Drupal\spectrum\Serializer\ModelSerializer;
use Drupal\spectrum\Query\Condition;

class ModelApiHandler extends BaseApiHandler
{
  private $modelClassName;

  public function __construct($modelClassName, $slug = null)
  {
    parent::__construct($slug);
    $this->modelClassName = $modelClassName;
  }

  public function get(Request $request)
  {
    $modelClassName = $this->modelClassName;
    $query = $modelClassName::getModelQuery();
    $result;

    if(empty($this->slug))
    {

    }
    else
    {
      $query->addCondition(new Condition($modelClassName::$idField, '=', $this->slug));
      $result = $query->fetchSingleModel();

      if(!empty($result))
      {
        $serializer = new ModelSerializer($result);
        $jsonapi = $serializer->toJsonApi()->serialize();
        return new Response(json_encode($jsonapi), 200, array());
      }
    }

    return new Response('OK', 200, array());
  }
}
