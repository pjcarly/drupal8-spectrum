<?php

namespace Drupal\spectrum\Serializer;

abstract class JsonApiBaseNode
{
  protected $id;
  protected $type;
  protected $links;

  public function setId($id)
  {
    $this->id = $id;
  }

  public function setType($type)
  {
    $this->type = $type;
  }

  public function hasType()
  {
    return !empty($this->type);
  }

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
