<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\SimpleCollectionWrapper;

use Drupal\spectrum\Models\Image;
use Drupal\spectrum\Models\File;

use Drupal\spectrum\Utils\AddressUtils;

class SimpleModelWrapper
{
  // This class exposes magic getters to get values from a model without having to know the drupal implementation
  // Useful for within Email templates for example, where we can just get {{ account.name }} instead of {{ account.entity.title.value }}
  private $model;

  public function __construct(Model $model)
  {
    $this->model = $model;
  }

  public function getModel() : Model
  {
    return $this->model;
  }

  public function getValue($underscoredField)
  {
    $model = $this->model;
    // email templates can't handle dashes, so we replaced them with underscores
    $prettyField = $model::getPrettyFieldForUnderscoredField($underscoredField);
    $prettyFieldsToFields = $model::getPrettyFieldsToFieldsMapping();

    if(array_key_exists($prettyField, $prettyFieldsToFields))
    {
      $fieldName = $prettyFieldsToFields[$prettyField];
      $fieldDefinition = $model->entity->getFieldDefinition($fieldName);

      $fieldType = $fieldDefinition->getType();
      $returnValue = '';

      // TODO: add support for field cardinality
      switch($fieldType){
        case 'autonumber':
          $returnValue = (int) $model->entity->get($fieldName)->value;
          break;
        case 'boolean':
          $returnValue = ($model->entity->get($fieldName)->value === '1');
          break;
        case 'decimal':
          $returnValue = (double) $model->entity->get($fieldName)->value;
          break;
        case 'geolocation':
          $lat = (float) $model->entity->get($fieldName)->lat;
          $lng = (float) $model->entity->get($fieldName)->lng;

          $returnValue = $lat.','.$lng;
          break;
        case 'entity_reference':
          if($fieldName === 'field_currency')
          {
            $returnValue = $model->entity->get($fieldName)->target_id;
          }

          break;
        case 'image':
          $fileId = $model->entity->get($fieldName)->target_id;

          if(!empty($fileId))
          {
            $returnValue = Image::forge(null, $fileId);
          }
          break;
        case 'file':
          $fileId = $model->entity->get($fieldName)->target_id;

          if(!empty($fileId))
          {
            $returnValue = File::forge(null, $fileId);
          }
          break;
        case 'uri':
          $returnValue = $model->entity->get($fieldName)->value;
          break;
        case 'link':
          $returnValue = $model->entity->get($fieldName)->uri;
          break;
        case 'address':
          $value = $model->entity->get($fieldName);

          $returnValue = AddressUtils::getAddress($value);
          break;
        case 'list_string':
          $alloweValues = $fieldDefinition->getFieldStorageDefinition()->getSetting('allowed_values');
          $value = $model->entity->get($fieldName)->value;

          if(array_key_exists($value, $alloweValues))
          {
            $returnValue = $alloweValues[$value];
          }
          else
          {
            $returnValue = '';
          }
          break;
        case 'created':
        case 'changed':
        case 'timestamp':
          // for some reason, created and changed aren't regular datetimes, they are unix timestamps in the database
          $timestamp = $model->entity->get($fieldName)->value;
          $returnValue = \DateTime::createFromFormat('U', $timestamp);
          break;
        case 'datetime':
          $dateValue = null;
          $attributeValue = $model->entity->get($fieldName)->value;

          if(!empty($attributeValue))
          {
            // We must figure out if this is a Date field or a datetime field
            // lets get the meta information of the field
            $fieldSettingsDatetimeType = $fieldDefinition->getItemDefinition()->getSettings()['datetime_type'];
            if($fieldSettingsDatetimeType === 'date')
            {
              $dateValue = new \DateTime($attributeValue);
            }
            else if($fieldSettingsDatetimeType === 'datetime')
            {
              $dateValue = new \DateTime($attributeValue);
              $dateValue->setTimezone(new \DateTimeZone('UTC'));
            }
          }

          $returnValue = $dateValue;
          break;
        default:
          $returnValue = $model->entity->get($fieldName)->value;
          break;
      }

      return $returnValue;
    }
    else
    {
      throw new InvalidFieldException();
    }
  }

  public function __get($property)
  {
    $model = $this->model;

    if(array_key_exists($property, $model->relatedViaFieldOnEntity)) // lets check for pseudo properties
    {
      $object = $model->relatedViaFieldOnEntity[$property];

      if($object instanceof Model)
      {
        return new SimpleModelWrapper($object);
      }
      else if($object instanceof Collection)
      {
        return new SimpleCollectionWrapper($object);
      }
      else
      {
        return null;
      }
    }
    else if(array_key_exists($property, $model->relatedViaFieldOnExternalEntity)) // lets check for pseudo properties
    {
      $object = $model->relatedViaFieldOnExternalEntity[$property];

      if($object instanceof Model)
      {
        return new SimpleModelWrapper($object);
      }
      else if($object instanceof Collection)
      {
        return new SimpleCollectionWrapper($object);
      }
      else
      {
        return null;
      }
    }
    else if($model::underScoredFieldExists($property))
    {
      try
      {
        $value = $this->getValue($property);
        return $value;
      }
      catch (InvalidFieldException $exception)
      {
        \Drupal::logger('Spectrum')->error('Property '.$property.' does not exist on modelclass '.get_called_class());
      }
    }
    else if(property_exists($model, $property))
    {
      $returnValue = $model->{$property};
      return $returnValue;
    }
    else if(Model::getterExists($model, $property))
    {
      return $model->callGetter($property);
    }
  }

  public function __isset($property)
  {
    $model = $this->model;
    // Needed for twig to be able to access relationship via magic getter
    $isSet = array_key_exists($property, $model->relatedViaFieldOnEntity) || array_key_exists($property, $model->relatedViaFieldOnExternalEntity) || $model::underScoredFieldExists($property) || property_exists($model, $property) || Model::getterExists($model, $property);
    return $isSet;
  }
}
