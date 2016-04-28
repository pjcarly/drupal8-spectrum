<?php

namespace Drupal\spectrum\Serializer;

class JsonApiRootNode extends JsonApiDataNode
{
  protected $included;

  public function addInclude(JsonApiNode $node)
  {
    if(empty($this->included))
    {
      $this->included = array();
    }

    $this->included[] = $node;
  }

  public function serialize()
  {
    $serialized = parent::serialize();

    if(!empty($this->included))
    {
      $serializedIncluded = array();
      foreach($his->included as $includedMember)
      {
        $serializedIncluded[] = $includedMember->serialize();
      }
      $serialized->included = $serializedIncluded;
    }

    return $serialized;
  }
}
