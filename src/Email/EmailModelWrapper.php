<?php

namespace Drupal\spectrum\Email;

use Drupal\spectrum\Model\Model;

class EmailModelWrapper
{
  private $model;

  public function __construct(Model $model)
  {
    $this->model = $model;
  }

  public function getValueForEmailTemplate($underscoredField)
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
            $returnValue = $datetime->format( 'c' );
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

    if(array_key_exists($property, $this->model->model->relatedViaFieldOnEntity)) // lets check for pseudo properties
    {
      return $this->model->relatedViaFieldOnEntity[$property];
    }
    else if(array_key_exists($property, $this->model->relatedViaFieldOnExternalEntity)) // lets check for pseudo properties
    {
      return $this->model->relatedViaFieldOnExternalEntity[$property];
    }
    else
    {
      try
      {
        return $this->getValueForEmailTemplate($property);
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
