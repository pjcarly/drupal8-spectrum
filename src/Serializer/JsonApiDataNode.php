<?php

namespace Drupal\spectrum\Serializer;

class JsonApiDataNode extends JsonApiBaseNode
{
  protected $data;
  protected $asArray = false;

  public function addNode(JsonApiNode $node)
  {
    if(empty($this->data) && !$this->asArray)
    {
      $this->data = $node;
    }
    else
    {
      if(!empty($this->data) && !is_array($this->data))
      {
        $firstNode = $this->data;
        $this->data = array($firstNode);
      }
      else if(empty($this->data))
      {
        $this->data = array();
      }

      $this->data[] = $node;
    }
  }

  public function asArray($asArray)
  {
    $this->asArray = $asArray;
  }

  public function serialize()
  {
    $serialized = new \stdClass();

    if(!empty($this->links))
    {
      $serialized->links = $this->getSerializedLinks();
    }

    if(is_array($this->data) || $this->asArray)
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

    return $serialized;
  }
}
