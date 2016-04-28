<?php

namespace Drupal\spectrum\Rest;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Serializer\JsonApiEmptyDataNode;

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
    $jsonapi;

    if(empty($this->slug))
    {
      $result = $query->fetchCollection();

      if(!$result->isEmpty)
      {
        $jsonapi = $result->serialize();
      }
      else
      {
        $node = new JsonApiEmptyDataNode();
        $node->asArray(true);
        $jsonapi = $node->serialize();
      }
    }
    else
    {
      $query->addCondition(new Condition($modelClassName::$idField, '=', $this->slug));
      $result = $query->fetchSingleModel();

      if(!empty($result))
      {
        $jsonapi = $result->serialize();
      }
      else
      {
        $node = new JsonApiEmptyDataNode();
        $jsonapi = $node->serialize();
      }
    }

    return new Response(json_encode($jsonapi), 200, array());
  }
}
