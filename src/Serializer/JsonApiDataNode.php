<?php

namespace Drupal\spectrum\Serializer;

class JsonApiDataNode extends JsonApiBaseNode
{
  protected $data;

  public function addNode(JsonApiNode $node)
  {
    if(empty($this->data))
    {
      $this->data = $node;
    }
    else
    {
      if(!is_array($this->data))
      {
        $firstNode = $this->data;
        $this->data = array($firstNode);
      }

      $this->data[] = $node;
    }
  }

  public function serialize()
  {
    $serialized = new \stdClass();

    if(is_array($this->data))
    {
      $serializedData = array();
      foreach($this->data as $dataMember)
      {
        $serializedData[] = $dataMember->serialize();
      }
      $serialized->data = $serializedData;
    }
    else
    {
      if(empty($this->data))
      {
        $serialized->data = new \stdClass;
      }
      else
      {
        $serialized->data = $this->data->serialize();
      }
    }

    if(!empty($this->links))
    {
      $serialized->links = $this->links;
    }

    return $serialized;
  }
}
