<?php

namespace Drupal\spectrum\Serializer;

class JsonApiEmptyDataNode extends JsonApiBaseNode
{
  private $asArray = false;

  public function asArray($asArray)
  {
    $this->asArray = $asArray;
  }

  public function serialize()
  {
    $serialized = new \stdClass();

    if($this->asArray)
    {
      $serialized->data = array();
    }
    else
    {
      $serialized->data = null;
    }

    return $serialized;
  }
}
