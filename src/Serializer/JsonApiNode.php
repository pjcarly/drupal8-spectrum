<?php

namespace Drupal\spectrum\Serializer;

/**
 * A JsonApiNode is the representation of a single entity. Upon deserialization it should be able to match the data back to the entity in the back-end
 * By using the type and ID
 */
class JsonApiNode extends JsonApiBaseNode
{
  /**
   * The Id of the entity represented by this node
   *
   * @var string|int
   */
  protected $id;

  /**
   * The type of the entity represented by this node
   *
   * @var string
   */
  protected $type;

  /**
   * An array containing the attributes of the entity that will be serialized in this node
   *
   * @var array
   */
  protected $attributes;

  /**
   * An array containing the relationships of the entity that will be serialized in this node
   *
   * @var array
   */
  protected $relationships;

  /**
   * Set the ID of this node
   *
   * @param string|int $id
   * @return JsonApiNode
   */
  public function setId($id) : JsonApiNode
  {
    $this->id = $id;
    return $this;
  }

  /**
   * Returns the ID of the node
   *
   * @return string|int|null
   */
  public function getId()
  {
    return $this->id;
  }

  /**
   * Set the type of the jsonapi node, that will match the entity that will be serialized in this node.
   * This is used in order to translate the node back to the entity upon deserialization
   *
   * @param string $type
   * @return JsonApiNode
   */
  public function setType(string $type) : JsonApiNode
  {
    $this->type = $type;
    return $this;
  }

  /**
   * Returns the type of the the entity serialized in the node
   *
   * @return string
   */
  public function getType() : string
  {
    return $this->type;
  }

  /**
   * Checks whether this node has a type
   *
   * @return boolean
   */
  public function hasType() : bool
  {
    return !empty($this->type);
  }

  /**
   * Add a relationship to the node
   *
   * @param string $name The name is the key in the serialized hash
   * @param JsonApiDataNode|null $node
   * @return JsonApiNode
   */
  public function addRelationship(string $name, ?JsonApiDataNode $node)  : JsonApiNode
  {
    if(empty($this->relationships))
    {
      $this->relationships = [];
    }

    $this->relationships[$name] = $node;
    return $this;
  }

  /**
   * Add an attribute to this node
   *
   * @param string $name The name is the key in the serialized hash
   * @param mixed $attribute
   * @return JsonApiNode
   */
  public function addAttribute(string $name, $attribute) : JsonApiNode
  {
    if(empty($this->attributes))
    {
      $this->attributes = [];
    }

    $this->attributes[$name] = $attribute;
    return $this;
  }

  /**
   * Remove an attribute from the attributes hash
   *
   * @param string $name
   * @return JsonApiNode
   */
  public function removeAttribute(string $name) : JsonApiNode
  {
    if(!empty($name) && array_key_exists($name, $this->attributes))
    {
      unset($this->attributes[$name]);
    }

    return $this;
  }

  /**
   * Rename a attribute to a different different name
   *
   * @param string $oldName The old name of the attribute, we will search for this in the attributes array
   * @param string $newName The new name of the attribute, this will be the new key in the serialized hash
   * @return JsonApiNode
   */
  public function renameAttribute(string $oldName, string $newName) : JsonApiNode
  {
    if(!empty($oldName) && !empty($newName) && array_key_exists($oldName, $this->attributes))
    {
      $this->attributes[$newName] = $this->attributes[$oldName];
      unset($this->attributes[$oldName]);
    }

    return $this;
  }

  /**
   * Remove a relationship by its name
   *
   * @param string $name
   * @return JsonApiNode
   */
  public function removeRelationship(string $name) : JsonApiNode
  {
    if(!empty($name) && array_key_exists($name, $this->relationships))
    {
      unset($this->relationships[$name]);
    }

    return $this;
  }

  /**
   * Rename a relationship to a different name, the new name will be the key in the serialized hash
   *
   * @param string $oldName The old name of the relationship, we will search for this in the relationships array
   * @param string $newName The new name of the relationship, this will be the new key in the serialized hash
   * @return JsonApiNode
   */
  public function renameRelationship(string $oldName, string $newName) : JsonApiNode
  {
    if(!empty($oldName) && !empty($newName) && array_key_exists($oldName, $this->relationships))
    {
      $this->relationships[$newName] = $this->relationships[$oldName];
      unset($this->attributes[$oldName]);
    }

    return $this;
  }

  /**
   * Returns the value of an attribute
   *
   * @param string $name the name of the attribute
   * @return mixed
   */
  public function getAttribute(string $name)
  {
    if(!empty($name) && array_key_exists($name, $this->attributes))
    {
      return $this->attributes[$name];
    }
  }

  /**
   * Returns the attributes array
   *
   * @return array|null
   */
  public function getAttributes() : ?array
  {
    return $this->attributes;
  }

  /**
   * Returns the relationships array
   *
   * @return array|null
   */
  public function getRelationships() : ?array
  {
    return $this->relationships;
  }

  /**
   * Get a relationshp by its name
   *
   * @param string $name
   * @return JsonApiDataNode|null
   */
  public function getRelationship(string $name) : ?JsonApiDataNode
  {
    if(!empty($name) && array_key_exists($name, $this->relationships))
    {
      return $this->relationships[$name];
    }
  }

  /**
   * Setrialize the hash into a PHP stdclass, which in turn can be serialized to JSON. The serialized json will be jsonapi.org compliant
   *
   * @return \stdClass
   */
  public function serialize() : \stdClass
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
      $serializedRelationships = [];
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
