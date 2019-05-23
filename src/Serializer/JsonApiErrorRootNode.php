<?php

namespace Drupal\spectrum\Serializer;

/**
 * A JsonApiErrorRootNode is the Root object of a jsonapi.org error response.
 * It includes 1 key "errors" which is an array of JsonApiErrorNodes
 */
class JsonApiErrorRootNode
{
  /**
   * An array containing all the error nodes
   *
   * @var JsonApiErrorNode[]
   */
  protected $errors = [];

  /**
   * Adds an error to this Root
   *
   * @param JsonApiErrorNode $error The error node you want to add
   * @return JsonApiLink
   */
  public function addError(JsonApiErrorNode $error) : JsonApiErrorRootNode
  {
    $this->errors[] = $error;
    return $this;
  }

  /**
   * Checks if this root has any errors
   *
   * @return boolean
   */
  public function hasErrors() : bool
  {
    return sizeof($this->errors) > 0;
  }

  /**
   * Returns a serialized version of this error root node
   *
   * @return \stdClass
   */
  public function serialize() : \stdClass
  {
    $serialized = new \stdClass();
    $serialized->errors = [];

    foreach($this->errors as $error)
    {
      $serialized->errors[] = $error->serialize();
    }

    return $serialized;
  }
}
