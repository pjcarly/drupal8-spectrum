<?php

namespace Drupal\spectrum\Serializer;

Use Drupal\spectrum\Utils\String;

abstract class ModelSerializerBase
{
  protected $modelName;

  function __construct($modelName)
  {
    $this->modelName = $modelName;
  }

  public function getPrettyFieldsToFieldsMapping()
  {
    $modelName = $this->modelName;

    $mapping = array();
    $fieldList = $modelName::getFieldList();

    foreach($fieldList as $key => $value)
    {
      $fieldnamepretty = String::dasherize(str_replace('field_', '', $key));
      $mapping[$fieldnamepretty] = $key;
    }

    return $mapping;
  }

  public function getFieldsToPrettyFieldsMapping()
  {
    $prettyMapping = $this->getPrettyFieldsToFieldsMapping();

    $mapping = array();
    foreach($prettyMapping as $pretty => $field)
    {
      $mapping[$field] = $pretty;
    }

    return $mapping;
  }
}
