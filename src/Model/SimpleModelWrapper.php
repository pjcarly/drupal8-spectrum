<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\SimpleCollectionWrapper;

class SimpleModelWrapper
{
  // This class exposes magic getters to get values from a model without having to know the drupal implementation
  // Useful for within Email templates for example, where we can just get {{ account.name }} instead of {{ account.entity.title.value }}
  private $model;

  public function __construct(Model $model)
  {
    $this->model = $model;
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
          $returnValue = (int) $model->entity->get($fieldName)->target_id;
          break;
        // If it is more than 1 item (or -1 in case of unlimited references), we must return an array
        case 'image':
          $returnValue = '/image/' . $model->entity->get($fieldName)->target_id;
          break;
        case 'file':
          $returnValue = '/image/' . $model->entity->get($fieldName)->target_id;
          break;
        case 'uri':
          $returnValue = $model->entity->get($fieldName)->value;
          break;
        case 'link':
          $returnValue = $model->entity->get($fieldName)->uri;
          break;
        case 'address':
          $address = $model->entity->get($fieldName);
          $attribute = null;
          if(!empty($address->country_code))
          {
            $returnValue .= $address->address_line1;
            $returnValue .= ', ' .$address->postal_code;
            $returnValue .= ' ' .$address->locality;
            $returnValue .= ', '.$address->country_code;

          }
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
            // for some reason, created and changed aren't regular datetimes, they are unix timestamps in the database
            $timestamp = $model->entity->get($fieldName)->value;
            $datetime = \DateTime::createFromFormat('U', $timestamp);
            $returnValue = $datetime->format('c');
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
    if(array_key_exists($property, $this->model->relatedViaFieldOnEntity)) // lets check for pseudo properties
    {
      $object = $this->model->relatedViaFieldOnEntity[$property];

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
    else if(array_key_exists($property, $this->model->relatedViaFieldOnExternalEntity)) // lets check for pseudo properties
    {
      $object = $this->model->relatedViaFieldOnExternalEntity[$property];

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
    else
    {
      try
      {
        return $this->getValue($property);
      }
      catch (InvalidFieldException $exception)
      {
        \Drupal::logger('Spectrum')->error('Property '.$property.' does not exist on modelclass '.get_called_class());
      }
    }
  }

  public function __isset($property)
  {
    $model = $this->model;
    // Needed for twig to be able to access relationship via magic getter
    return array_key_exists($property, $model->relatedViaFieldOnEntity) || array_key_exists($property, $model->relatedViaFieldOnExternalEntity) || $model::underScoredFieldExists($property);
  }
}
