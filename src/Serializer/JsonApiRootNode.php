<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Exceptions\InvalidTypeException;

class JsonApiRootNode extends JsonApiDataNode
{
  protected $included;

  public function addInclude($jsonapi)
  {
    if(empty($this->included))
    {
      $this->included = array();
    }

    if($jsonapi instanceof JsonApiNode)
    {
      $this->included[] = $node;
    }
    else if($jsonapi instanceof JsonApiDataNode)
    {
      if(is_array($jsonapi->data))
      {
        foreach($jsonapi->data as $jsonapiNode)
        {
          $this->included[] = $jsonapiNode;
        }
      }
      else
      {
        $this->included[] = $jsonapi->data;
      }
    }
    else
    {
      throw new InvalidTypeException();
    }
  }

  public function serialize()
  {
    $serialized = parent::serialize();

    if(!empty($this->included))
    {
      $serializedIncluded = array();
      foreach($this->included as $includedMember)
      {
        $serializedIncluded[] = $includedMember->serialize();
      }
      $serialized->included = $serializedIncluded;
    }

    return $serialized;
  }

  public function setData(JsonApiDataNode $node)
  {
    if($this->asArray && !is_array($node->data))
    {
      $this->data = array();
      $this->data[] = $node->data;
    }
    else
    {
      $this->data = $node->data;
    }
  }
}
