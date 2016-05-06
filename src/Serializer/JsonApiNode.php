<?php

namespace Drupal\spectrum\Serializer;

class JsonApiNode extends JsonApiBaseNode
{
  protected $id;
  protected $type;
  protected $attributes;
  protected $relationships;

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

    if(!empty($this->links))
    {
      $serialized->links = $this->getSerializedLinks();
    }

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

    return $serialized;
  }
}
