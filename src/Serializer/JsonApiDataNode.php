<?php

namespace Drupal\spectrum\Serializer;

class JsonApiDataNode extends JsonApiBaseNode
{
  protected $data;
  protected $asArray = false;

  public function clearData() : void
  {
    unset($this->data);
  }

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

  public function getData()
  {
    return $this->data;
  }

  public function asArray($asArray)
  {
    $this->asArray = $asArray;
  }

  public function serialize() : \stdClass
  {
    $serialized = new \stdClass();

    if(!empty($this->links))
    {
      $serialized->links = $this->getSerializedLinks();
    }

    if(!empty($this->meta))
    {
      $serialized->meta = $this->meta;
    }

    if(is_array($this->data) || ($this->asArray))
    {
      $serializedData = array();
      if(!empty($this->data))
      {
        foreach($this->data as $dataMember)
        {
          $serializedData[] = $dataMember->serialize();
        }
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
