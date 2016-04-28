<?php

namespace Drupal\spectrum\Serializer;

abstract class JsonApiBaseNode
{
  protected $links;

  public function addLink($name, $link)
  {
    if($this->links === null)
    {
      $this->links = array();
    }

    $this->links[$name] = $link;
  }

  public abstract function serialize();
}
