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

  public function getType()
  {
    return $this->type;
  }

  public function hasType()
  {
    return !empty($this->type);
  }

  public function addRelationship($name, ?JsonApiDataNode $node)
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

  public function removeAttribute($name)
  {
    if(!empty($name) && array_key_exists($name, $this->attributes))
    {
      unset($this->attributes[$name]);
    }
  }

  public function renameAttribute($oldName, $newName)
  {
    if(!empty($oldName) && !empty($newName) && array_key_exists($oldName, $this->attributes))
    {
      $this->attributes[$newName] = $this->attributes[$oldName];
      unset($this->attributes[$oldName]);
    }
  }

  public function removeRelationship($name)
  {
    if(!empty($name) && array_key_exists($name, $this->relationships))
    {
      unset($this->relationships[$name]);
    }
  }

  public function renameRelationship($oldName, $newName)
  {
    if(!empty($oldName) && !empty($newName) && array_key_exists($oldName, $this->relationships))
    {
      $this->relationships[$newName] = $this->relationships[$oldName];
      unset($this->attributes[$oldName]);
    }
  }

  public function getAttribute($name)
  {
    if(!empty($name) && array_key_exists($name, $this->attributes))
    {
      return $this->attributes[$name];
    }
  }

  public function getRelationship($name)
  {
    if(!empty($name) && array_key_exists($name, $this->relationship))
    {
      return $this->relationship[$name];
    }
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
        $relationship = $this->relationships[$relationshipName];
        if(!empty($relationship))
        {
          $serializedRelationships[$relationshipName] = $relationship->serialize();
        }
      }
      $serialized->relationships = $serializedRelationships;
    }

    return $serialized;
  }
}
