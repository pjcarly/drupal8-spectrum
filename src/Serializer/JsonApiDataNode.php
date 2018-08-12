<?php

namespace Drupal\spectrum\Serializer;

/**
 * A JsonApiDataNode is a json-api node that has a data attribute, which contains one or more other JsonaApiNodes
 */
class JsonApiDataNode extends JsonApiBaseNode
{
  /**
   * The hash containing either 1 or more JsonApiNodes
   *
   * @var JsonApiNode|JsonApiNode[]
   */
  protected $data;

  /**
   * This variable is a flag to determine how the data hash should be serialized, if this flag is true, even if there is only 1 result
   * in the data, it will be serialized in an array containing 1 item
   *
   * @var boolean
   */
  protected $asArray = false;

  /**
   * Removes all the data from this node
   *
   * @return JsonApiDataNode
   */
  public function clearData() : JsonApiDataNode
  {
    unset($this->data);
    return $this;
  }

  /**
   * Add a JsonApiNode to the data attribute
   *
   * @param JsonApiNode $node
   * @return JsonApiDataNode
   */
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

    return $this;
  }

  /**
   * Returns the data hash
   *
   * @return JsonApiNode|JsonApiNode[]
   */
  public function getData()
  {
    return $this->data;
  }

  /**
   * Set how the results should be serialized, by setting this flag to true, the result will be serialized as an array, even if there is only 1 node inside
   * This flag has no effect if there are multiple values in the data array, and setting it to false.
   *
   * @param boolean $asArray
   * @return JsonApiDataNode
   */
  public function asArray(bool $asArray) : JsonApiDataNode
  {
    $this->asArray = $asArray;
    return $this;
  }

  /**
   * Serializes the current structure in a stdclass that can be serialized to json in a jsonapi.org compliant way
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
