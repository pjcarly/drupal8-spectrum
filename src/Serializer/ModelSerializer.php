<?php

namespace Drupal\spectrum\Serializer;

Use Drupal\spectrum\Utils\String;

class ModelSerializer extends ModelSerializerBase
{
  private $model;

  function __construct($model)
  {
    parent::__construct(get_class($model));
    $this->model = $model;
  }

  function serialize($type)
  {
    switch($type)
    {
      case 'json-api':
      default:
        return $this->toJsonApi($this->model);
      break;
    }
  }

  public function toJsonApi()
  {
    $model = $this->model;

    $ignore_fields = array('revision_log', 'vid', 'revision_timestamp', 'revision_uid', 'revision_log', 'revision_translation_affected', 'revision_translation_affected', 'default_langcode', 'path', 'content_translation_source', 'content_translation_outdated');
    $manual_fields = array($model::$idField, 'type');

    $jsonApiRecord = new \stdClass;
    $attributes = new \stdClass;
    $relationships = new \stdClass;

    $fieldToPrettyMapping = $this->getFieldsToPrettyFieldsMapping();
    $fieldDefinitions = $model::getFieldDefinitions();

    foreach($fieldDefinitions as $fieldName => $fieldDefinition)
    {
      // First let's check the manual fields
      if($fieldName === 'type')
      {
        $jsonApiRecord->type = $model->entity->get($fieldName)->target_id;
      }
      else if($fieldName === $model::$idField)
      {
        $jsonApiRecord->id = $model->entity->get($fieldName)->value;
      }

      // Now we'll check the other fields
      if(!in_array($fieldName, $ignore_fields) && !in_array($fieldName, $manual_fields))
      {
        $fieldNamePretty = $fieldToPrettyMapping[$fieldName];
        switch ($fieldDefinition->getType())
        {
          case 'geolocation':
            $attributes->$fieldNamePretty = new \stdClass();
            $attributes->$fieldNamePretty->lat = $model->entity->get($fieldName)->lat;
            $attributes->$fieldNamePretty->lng = $model->entity->get($fieldName)->lng;
            break;
          case 'entity_reference':
            if(!empty($model->entity->get($fieldName)->entity))
            {
              $relationships->$fieldNamePretty = new \stdClass();
              $relationships->$fieldNamePretty->data = new \stdClass();
              $relationships->$fieldNamePretty->data->id = $model->entity->get($fieldName)->target_id;
              $relationships->$fieldNamePretty->data->type = $model->entity->get($fieldName)->entity->bundle();
            }
            break;
          //case 'datetime':
            //throw new \Drupal\spectrum\Exceptions\NotImplementedException();
            //break;
          default:
            $attributes->$fieldNamePretty = $model->entity->get($fieldName)->value;
            break;
        }
      }
    }

    $jsonApiRecord->attributes = $attributes;
    $jsonApiRecord->relationships = $relationships;

    return $jsonApiRecord;
  }
}
