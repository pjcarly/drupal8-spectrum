<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Exceptions\InvalidTypeException;

/**
 * A JsonApiRootNode, is a node that is the root of your serialization process, it can only be used once, as it includes the hash "includes"
 * Which contains the included records which are a different typem but are related to the node or nodes being returned by the endpoint
 */
class JsonApiRootNode extends JsonApiDataNode
{
  /**
   * Holds a list of JsonApiNodes which should be Included with the original results
   *
   * @var array
   */
  protected $included;

  /**
   * Add an include that will be added to the response, in the included hash.
   * These records are of a different type than the root records, but are related through relationships
   *
   * @param JsonApiBaseNode $jsonapi
   * @return JsonApiRootNode
   */
  public function addInclude(JsonApiBaseNode $jsonapi) : JsonApiRootNode
  {
    if($jsonapi instanceof JsonApiNode)
    {
      $this->addJsonApiNodeToIncludes($jsonapi);
    }
    else if($jsonapi instanceof JsonApiDataNode)
    {
      if(is_array($jsonapi->data))
      {
        foreach($jsonapi->data as $jsonapiNode)
        {
          $this->addJsonApiNodeToIncludes($jsonapiNode);
        }
      }
      else if(!empty($jsonapi->data))
      {
        $this->addJsonApiNodeToIncludes($jsonapi->data);
      }
    }
    else
    {
      throw new InvalidTypeException();
    }

    return $this;
  }

  /**
   * Returns the Included jsonapi nodes
   *
   * @return JsonApiNode[]
   */
  public function getIncluded() : array
  {
    if(empty($this->included))
    {
      return [];
    }

    return $this->included;
  }

  /**
   * Removes a included node by its key in the included array
   *
   * @param string $key
   * @return JsonApiRootNode
   */
  public function removeIncluded(string $key) : JsonApiRootNode
  {
    if(!empty($this->included) && array_key_exists($key, $this->included))
    {
      unset($this->included[$key]);
    }

    return $this;
  }

  /**
   * Adds a JsonApiNode to the Included hash. In case the Key/Type combination already exists, the existing node will be overriden. That way no records will be in the serialized hash
   *
   * @param JsonApiNode $node
   * @return JsonApiRootNode
   */
  private function addJsonApiNodeToIncludes(JsonApiNode $node) : JsonApiRootNode
  {
    if(empty($this->included))
    {
      $this->included = [];
    }

    $key = $node->getUniqueKey();
    $this->included[$key] = $node;

    return $this;
  }

  /**
   * Returns a serialized version of the rootnode, this in turn can be serialized to json.
   *
   * @return \stdClass
   */
  public function serialize() : \stdClass
  {
    $serialized = parent::serialize();

    if(!empty($this->included))
    {
      $serializedIncluded = [];

      foreach($this->included as $includedMember)
      {
        $serializedIncluded[] = $includedMember->serialize();
      }

      $serialized->included = $serializedIncluded;
    }

    return $serialized;
  }

  /**
   * This function copies the data attribute of the provided JsonApiDataNode into this rootnode
   *
   * @param JsonApiDataNode $node
   * @return JsonApiRootNode
   */
  public function setData(JsonApiDataNode $node) : JsonApiRootNode
  {
    if($this->asArray && !is_array($node->data))
    {
      $this->data = [];
      if(!empty($node->data))
      {
        $this->data[] = $node->data;
      }
    }
    else
    {
      $this->data = $node->data;
    }

    return $this;
  }

  /**
   * This function copies the meta attribute of the provided jsonapidatanode in this rootnode
   *
   * @param JsonApiDataNode $node
   * @return JsonApiRootNode
   */
  public function setMeta(JsonApiDataNode $node) : JsonApiRootNode
  {
    foreach($node->getMeta() as $key => $value)
    {
      $this->addMeta($key, $value);
    }

    return $this;
  }

  /**
   * This function extracts the none default keys in the jsonapiroot node. The default keys are the ones provided by the jsonapi.org spec
   * Every other key in the hash will be returned.
   *
   * @param \stdClass $jsonapidocument
   * @return array
   */
  public static function getNoneDefaultDataKeys(\stdClass $jsonapidocument) : array
  {
    $noneDefaultKeys = [];
    $standardKeys = ['type', 'id', 'attributes', 'relationships', 'links'];
    foreach($jsonapidocument->data as $key => $value)
    {
      if(!in_array($key, $standardKeys))
      {
        $noneDefaultKeys[] = $key;
      }
    }

    return $noneDefaultKeys;
  }
}
