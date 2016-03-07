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

    foreach($model->entity->getFields() as $field)
    {
      $definition = $field->getFieldDefinition();
      $fieldname = $field->getName();

      // First let's check the manual fields
      if($fieldname === 'type')
      {
        $jsonApiRecord->type = $model->entity->get($fieldname)->target_id;
      }
      else if($fieldname === $model::$idField)
      {
        $jsonApiRecord->id = $model->entity->get($fieldname)->value;
      }

      // Now we'll check the other fields
      if(!in_array($fieldname, $ignore_fields) && !in_array($fieldname, $manual_fields))
      {
        $fieldnamepretty = $fieldToPrettyMapping[$fieldname];

        switch ($definition->getType()) {
          case 'geolocation':
            $attributes->$fieldnamepretty->lat = $model->entity->get($fieldname)->lat;
            $attributes->$fieldnamepretty->lng = $model->entity->get($fieldname)->lng;
            break;
          case 'entity_reference':
            $relationships->$fieldnamepretty->data->id = $model->entity->get($fieldname)->target_id;
            $relationships->$fieldnamepretty->data->type = $model->entity->get($fieldname)->entity->bundle();
            break;
          default:
            $attributes->$fieldnamepretty = $model->entity->get($fieldname)->value;
            break;
        }
      }
    }

    $jsonApiRecord->attributes = $attributes;
    $jsonApiRecord->relationships = $relationships;

    return $jsonApiRecord;
  }
}
