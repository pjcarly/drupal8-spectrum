<?php

namespace Drupal\spectrum\Serializer;

/**
 * JsonApiBaseNode is an abstract class, that encapsulates functionality that is found on most jsonapi.org nodes.
 * All nodes can contain a meta and a links hash, containing meta information, and links to other endpoints respectively
 */
abstract class JsonApiBaseNode
{
  /**
   * Contains the links of the Node
   *
   * @var array
   */
  protected $links;

  /**
   * Contains the meta of the Node
   *
   * @var array
   */
  protected $meta;

  /**
   * Removes all meta information already added to the node
   *
   * @return JsonApiBaseNode
   */
  public function clearMeta(): JsonApiBaseNode
  {
    unset($this->meta);
    return $this;
  }

  /**
   * Add meta information to the node's hash. The key is unique, in case a duplicate key is provided, the previous entry will be overridden
   *
   * @param string $key
   * @param int|string|mixed $value
   * @return JsonApiBaseNode
   */
  public function addMeta(string $key, $value): JsonApiBaseNode
  {
    if ($this->meta === null) {
      $this->meta = [];
    }

    $this->meta[$key] = $value;
    return $this;
  }

  /**
   * Adds a JsonApiLink to the link information in the node, the key is unique, in case a duplicate key was provided, the previous entry will be overridden
   *
   * @param string $name
   * @param JsonApiLink $link
   * @return JsonApiBaseNode
   */
  public function addLink(string $name, JsonApiLink $link): JsonApiBaseNode
  {
    if ($this->links === null) {
      $this->links = [];
    }

    $this->links[$name] = $link;
    return $this;
  }

  /**
   * Returns a JsonApiLink by its key
   *
   * @param string $name
   * @return JsonApiLink
   */
  public function getLink(string $name): JsonApiLink
  {
    return $this->links[$name];
  }

  /**
   * Returns an array of serialized JsonApiLinks
   *
   * @return array
   */
  public function getSerializedLinks(): array
  {
    $serializedLinks = [];
    foreach ($this->links as $key => $link) {
      $serializedLinks[$key] = $link->getUrl();
    }

    return $serializedLinks;
  }

  /**
   * Returns the meta information as an array
   *
   * @return array
   */
  public function getMeta(): array
  {
    return empty($this->meta) ? [] : $this->meta;
  }

  /**
   * Every implementation of JsonApiBaseNode should implement the serialize function, which returns a stdClass that can be serialized to Json
   *
   * @return \stdClass
   */
  public abstract function serialize(): \stdClass;
}
