<?php

namespace Drupal\spectrum\Serializer;

class JsonApiLink
{
  public $name;
  public $params;
  public $baseUrl;

  public function __construct($name, $baseUrl)
  {
    $this->name = $name;
    $this->baseUrl = $baseUrl;
    $this->params = array();
  }

  public function addParam($name, $value)
  {
    $this->params[$name] = $value;
  }

  public function getUrl()
  {
    return empty($this->params) ? $this->baseUrl : $this->baseUrl . '?' . http_build_query($this->params);
  }
}
