<?php

namespace Drupal\spectrum\Serializer;

class JsonApiNode extends JsonApiBaseNode
{
  protected $attributes;
  protected $relationships;

  public function addRelationship($name, JsonApiDataNode $node)
  {
    if(empty($this->relationships))
    {
      $this->relationships = array();
    }

    $this->relationships[$name] = $node;
  }

  public function addAttribute($name, $attribute)
  {
    if(empty($this->attributes))
    {
      $this->attributes = array();
    }

    $this->attributes[$name] = $attribute;
  }

  public function serialize()
  {
    $serialized = new \stdClass();
    $serialized->id = $this->id;
    $serialized->type = $this->type;

    if(!empty($this->attributes))
    {
      $serialized->attributes = $this->attributes;
    }

    if(!empty($this->relationships))
    {
      $serializedRelationships = array();
      foreach(array_keys($this->relationships) as $relationshipName)
      {
        $serializedRelationships[$relationshipName] = $this->relationships[$relationshipName]->serialize();
      }
      $serialized->relationships = $serializedRelationships;
    }

    if(!empty($this->links))
    {
      $serialized->links = $this->links;
    }

    return $serialized;
  }
}
