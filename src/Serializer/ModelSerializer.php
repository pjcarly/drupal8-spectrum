<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Utils\String;

class ModelSerializer extends ModelSerializerBase
{
  private $model;

  function __construct($model)
  {
    parent::__construct(get_class($model));
    $this->model = $model;
  }

  function serialize($type)
  {
    switch($type)
    {
      case 'json-api':
      default:
        return $this->toJsonApi($this->model);
      break;
    }
  }

  public function toJsonApi()
  {
    $root = new JsonApiRootNode();
    $node = $this->model->getJsonApiNode();
    $root->addNode($node);

    return $root;
  }
}
