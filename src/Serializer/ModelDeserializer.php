<?php

namespace Drupal\spectrum\Serializer;

Use Drupal\spectrum\Utils\String;

class ModelDeserializer extends ModelSerializerBase
{
  function __construct($modelName)
  {
    parent::__construct($modelName);
  }

  function deserialize($type, $serialized)
  {
    switch($type)
    {
      case 'json-api':
      default:
        return $this->fromJsonApi($serialized);
      break;
    }
  }

  public function fromJsonApi($serialized)
  {
    $deserialized = json_decode($serialized);
    $prettyFieldMapping = $this->getPrettyFieldsToFieldsMapping();

    dump($prettyFieldMapping);
    $fieldNames = array();
    foreach($deserialized->bookingRequest as $key => $value)
    {
      dump($key);
      $fieldName = $prettyFieldMapping[$key];
      $fieldNames[$fieldName] = $value;
    }

    dump($fieldNames);
  }
}
