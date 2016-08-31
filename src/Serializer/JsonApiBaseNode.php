<?php

namespace Drupal\spectrum\Serializer;

abstract class JsonApiBaseNode
{
  protected $links;
  protected $meta;

  public function addMeta($key, $value)
  {
    if($this->meta === null)
    {
      $this->meta = array();
    }

    $this->meta[$key] = $value;
  }

  public function addLink($name, JsonApiLink $link)
  {
    if($this->links === null)
    {
      $this->links = array();
    }

    $this->links[$name] = $link;
  }

  public function getLink($name)
  {
    return $this->links[$name];
  }

  public function getSerializedLinks()
  {
    $serializedLinks = array();
    foreach(array_keys($this->links) as $linkName)
    {
      $serializedLinks[$linkName] = $this->links[$linkName]->getUrl();
    }

    return $serializedLinks;
  }

  public abstract function serialize();
}
