<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiNode;
use Drupal\spectrum\Serializer\JsonApiDataNode;
use Drupal\spectrum\Model\Collection;
Use Drupal\spectrum\Utils\StringUtils;

trait ModelSerializerMixin
{
  // This method returns the current Model as a JsonApiNode (jsonapi.org)
  public static function getIgnoreFields()
  {
    return array('type', 'revision_log', 'vid', 'revision_timestamp', 'revision_uid', 'revision_log', 'revision_translation_affected', 'revision_translation_affected', 'default_langcode', 'path', 'content_translation_source', 'content_translation_outdated', 'pass', 'uuid', 'langcode', 'metatag', 'field_meta_tags', 'menu_link', 'roles');
  }

  public function getValueToSerialize($fieldName, $fieldDefinition = null)
  {
    $fieldDefinition = empty($fieldDefinition) ? static::getFieldDefinition($fieldName) : $fieldDefinition;
    $valueToSerialize = null;
    $fieldType = $fieldDefinition->getType();

    switch ($fieldType)
    {
      case 'address':
        $address = $this->entity->get($fieldName);
        $attribute = null;
        if(!empty($address->country_code))
        {
          $attribute = new \stdClass();
          $attribute->{'country-code'} = $address->country_code;
          $attribute->{'administrative-area'} = $address->administrative_area;
          $attribute->{'locality'} = $address->locality;
          $attribute->{'dependent-locality'} = $address->dependent_locality;
          $attribute->{'postal-code'} = $address->postal_code;
          $attribute->{'sorting-code'} = $address->sorting_code;
          $attribute->{'address-line1'} = $address->address_line1;
          $attribute->{'address-line2'} = $address->address_line2;
        }
        $valueToSerialize = $attribute;
        break;
      case 'autonumber':
        $valueToSerialize = (int) $this->entity->get($fieldName)->value;
        break;
      case 'boolean':
        $valueToSerialize = ($this->entity->get($fieldName)->value === '1');
        break;
      case 'changed':
      case 'created':
      case 'timestamp':
        // for some reason, created and changed aren't regular datetimes, they are unix timestamps in the database
        $timestamp = $this->entity->get($fieldName)->value;
        $datetime = \DateTime::createFromFormat('U', $timestamp);
        $valueToSerialize = $datetime->format('c');
        break;
      case 'datetime':
        $dateValue = null;
        $attributeValue = $this->entity->get($fieldName)->value;

        if(!empty($attributeValue))
        {
          // We must figure out if this is a Date field or a datetime field
          // lets get the meta information of the field
          $fieldSettingsDatetimeType = $fieldDefinition->getItemDefinition()->getSettings()['datetime_type'];
          if($fieldSettingsDatetimeType === 'date')
          {
            $dateValue = new \DateTime($attributeValue);
            $dateValue = $dateValue->format('Y-m-d');
          }
          else if($fieldSettingsDatetimeType === 'datetime')
          {
            $dateValue = new \DateTime($attributeValue);
            $dateValue->setTimezone(new \DateTimeZone('UTC'));
            $dateValue = $dateValue->format('Y-m-d\TH:i:s');
          }
        }

        $valueToSerialize = $dateValue;
        break;
      case 'decimal':
        $valueToSerialize = (double) $this->entity->get($fieldName)->value;
        break;
      case 'entity_reference':
        // TODO: this is really hacky, we must consider finding a more performant solution than the one with the target_ids now
        if(!empty($this->entity->get($fieldName)->entity))
        {
          $relationshipDataNode = new JsonApiDataNode();

          // Lets also check the cardinality of the field (amount of references the field can contain)
          // If it is more than 1 item (or -1 in case of unlimited references), we must return an array
          $fieldCardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();
          if($fieldCardinality !== 1)
          {
            $relationshipDataNode->asArray(true);
          }

          $idsThatHaveBeenset = array();
          foreach($this->entity->get($fieldName) as $referencedEntity)
          {
            $target_id = $referencedEntity->target_id;

            if(!array_key_exists($target_id, $idsThatHaveBeenset))
            {
              $idsThatHaveBeenset[$target_id] = $target_id;
              $relationshipNode = new JsonApiNode();
              $relationshipNode->setId($referencedEntity->target_id);
              $relationshipNode->setType($referencedEntity->entity->bundle());
              $relationshipDataNode->addNode($relationshipNode);
            }
          }

          if($fieldName === 'field_currency')
          {
            $valueToSerialize = $referencedEntity->target_id;
          }
          else
          {
            $valueToSerialize = $relationshipDataNode;
          }
        }
        break;
      case 'file':
        if(!empty($this->entity->get($fieldName)->entity))
        {
          $attribute = new \stdClass();
          $attribute->id = $this->entity->get($fieldName)->target_id;
          $attribute->filename = $this->entity->get($fieldName)->entity->get('filename')->value;
          $attribute->uri = $this->entity->get($fieldName)->entity->get('uri')->value;
          $attribute->url = $this->entity->get($fieldName)->entity->url();
          $attribute->filemime = $this->entity->get($fieldName)->entity->get('filemime')->value;
          $attribute->filesize = $this->entity->get($fieldName)->entity->get('filesize')->value;

          $valueToSerialize = $attribute;
        }
        else
        {
          $valueToSerialize = null;
        }
        break;
      case 'geolocation':
        $attribute = null;
        if(!empty($this->entity->get($fieldName)->lat))
        {
          $attribute = new \stdClass();
          $attribute->lat = (float) $this->entity->get($fieldName)->lat;
          $attribute->lng = (float) $this->entity->get($fieldName)->lng;
        }
        $valueToSerialize = $attribute;
        break;
      case 'integer':
        $valueToSerialize = (int) $this->entity->get($fieldName)->value;
        break;
      case 'image':
        $fileEntity = $this->entity->get($fieldName)->entity;

        if(!empty($fileEntity))
        {
          $attribute = new \stdClass();
          $attribute->id = $this->entity->get($fieldName)->target_id;
          $attribute->filename = $fileEntity->get('filename')->value;
          $attribute->filemime = $fileEntity->get('filemime')->value;
          $attribute->filesize = $fileEntity->get('filesize')->value;
          $attribute->width = $this->entity->get($fieldName)->width;
          $attribute->height = $this->entity->get($fieldName)->height;
          $attribute->alt = $this->entity->get($fieldName)->alt;
          $attribute->title = $this->entity->get($fieldName)->title;

          $request = \Drupal::request();
          $attribute->url = $request->getSchemeAndHttpHost() . $request->getBasePath() . '/image/' . $fileEntity->get('filename')->value . '/?fid=' . $attribute->id . '&dg=' . md5($fileEntity->get('uuid')->value);

          $valueToSerialize = $attribute;
        }
        else
        {
          $valueToSerialize = null;
        }
        break;
      case 'link':
        $valueToSerialize = $this->entity->get($fieldName)->uri;
        break;
      case 'uri':
        $valueToSerialize = $this->entity->get($fieldName)->value;
        //$node->addAttribute('url', file_create_url($this->entity->get($fieldName)->value));
        break;
      default:
        $fieldCardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();
        $value;

        if($fieldCardinality !== 1)
        {
          // More than 1 value allowed in the field
          $value = [];
          $fieldValues = $this->entity->get($fieldName);
          foreach($fieldValues as $fieldValue)
          {
            $value[] = $fieldValue->value;
          }
        }
        else
        {
          $value = $this->entity->get($fieldName)->value;
        }

        $valueToSerialize = $value;
        break;
    }

    return $valueToSerialize;
  }

  public function getJsonApiNode()
  {
    $node = new JsonApiNode();

    $ignoreFields = static::getIgnoreFields();
    $fieldToPrettyMapping = static::getFieldsToPrettyFieldsMapping();
    $fieldDefinitions = static::getFieldDefinitions();

    foreach($fieldDefinitions as $fieldName => $fieldDefinition)
    {
      // First let's check the manual fields
      if($fieldName === 'type')
      {
        $node->setType(StringUtils::dasherize($this->entity->get($fieldName)->target_id));
        continue;
      }
      else if($fieldName === static::$idField)
      {
        $node->setId($this->entity->get($fieldName)->value);
        continue;
      }

      // Now we'll check the other fields
      if(!in_array($fieldName, $ignoreFields))
      {
        $fieldNamePretty = $fieldToPrettyMapping[$fieldName];
        $fieldType = $fieldDefinition->getType();

        $valueToSerialize = $this->getValueToSerialize($fieldName, $fieldDefinition);

        if($fieldType === 'entity_reference')
        {
          if($fieldName === 'field_currency')
          {
            $node->addAttribute($fieldNamePretty, $valueToSerialize);
          }
          else
          {
            $node->addRelationship($fieldNamePretty, $valueToSerialize);
          }
        }
        else
        {
          $node->addAttribute($fieldNamePretty, $valueToSerialize);
        }
      }
    }

    // some entity types don't have a type field, we must rely on static definitions
    if(!$node->hasType())
    {
      // some entity types don't have a bundle (user for example) so we must rely on the entity type itself
      if(empty(static::$bundle))
      {
        $node->setType(static::$entityType);
      }
      else
      {
        $node->setType(static::$bundle);
      }
    }

    return $node;
  }

  public function serialize()
  {
    $root = new JsonApiRootNode();
    $node = $this->getJsonApiNode();
    $root->addNode($node);

    return $root->serialize();
  }

  public static function getTypePrettyFieldToFieldsMapping()
  {
    $mapping = array();
    $mapping['address'] = array();
    $mapping['address']['country-code'] = 'country_code';
    $mapping['address']['administrative-area'] = 'administrative_area';
    $mapping['address']['locality'] = 'locality';
    $mapping['address']['dependent-locality'] = 'dependent-locality';
    $mapping['address']['postal-code'] = 'postal_code';
    $mapping['address']['sorting-code'] = 'sorting_code';
    $mapping['address']['address-line1'] = 'address_line1';
    $mapping['address']['address-line2'] = 'address_line2';
    return $mapping;
  }

  public static function getTypeFieldToPrettyFielsMapping()
  {
    $prettyMapping = static::getTypePrettyFieldToFieldsMapping();
    $mapping = array();

    foreach($prettyMapping as $type => $prettyFieldsMapping)
    {
      $mapping[$type] = array();
      foreach($prettyFieldsMapping as $prettyField => $localField)
      {
        $mapping[$type][$localField] = $prettyField;
      }
    }

    return $mapping;
  }

  // This function returns a mapping of the different fields, with "field_" stripped, and a dasherized representation of the field name
  public static function getPrettyFieldsToFieldsMapping()
  {
    $mapping = array();
    $fieldList = static::getFieldDefinitions();

    foreach($fieldList as $key => $value)
    {
      if($key !== 'title')
      {
        $fieldnamepretty = trim(trim(StringUtils::dasherize(str_replace('field_', '', $key)), '-'));
      }
      else
      {
        $fieldnamepretty = 'name';
      }
      $mapping[$fieldnamepretty] = $key;
    }

    return $mapping;
  }

  // This function returns the inverse of getPrettyFieldsToFieldsMapping(), for mapping pretty fields back to the original
  public static function getFieldsToPrettyFieldsMapping()
  {
    $prettyMapping = static::getPrettyFieldsToFieldsMapping();

    $mapping = array();
    foreach($prettyMapping as $pretty => $field)
    {
      $mapping[$field] = $pretty;
    }

    return $mapping;
  }

  public static function getFieldForPrettyField($prettyField)
  {
    $field = null;
    $prettyToFieldsMap = static::getPrettyFieldsToFieldsMapping();

    if(array_key_exists($prettyField, $prettyToFieldsMap))
    {
      $field = $prettyToFieldsMap[$prettyField];
    }

    return $field;
  }

  public static function prettyFieldExists($prettyField)
  {
    $field = static::getFieldForPrettyField($prettyField);
    return !empty($field);
  }
}
