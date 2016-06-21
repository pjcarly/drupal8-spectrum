<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiNode;
use Drupal\spectrum\Serializer\JsonApiDataNode;

Use Drupal\spectrum\Utils\StringUtils;

trait ModelSerializerMixin
{
  // This method returns the current Model as a JsonApiNode (jsonapi.org)
  public function getJsonApiNode()
  {
    $node = new JsonApiNode();

    $ignore_fields = array('revision_log', 'vid', 'revision_timestamp', 'revision_uid', 'revision_log', 'revision_translation_affected', 'revision_translation_affected', 'default_langcode', 'path', 'content_translation_source', 'content_translation_outdated', 'pass', 'uuid', 'langcode');
    $manual_fields = array($this::$idField, 'type');

    $fieldToPrettyMapping = static::getFieldsToPrettyFieldsMapping();
    $fieldDefinitions = static::getFieldDefinitions();

    foreach($fieldDefinitions as $fieldName => $fieldDefinition)
    {
      // First let's check the manual fields
      if($fieldName === 'type')
      {
        $node->setType($this->entity->get($fieldName)->target_id);
      }
      else if($fieldName === static::$idField)
      {
        $node->setId($this->entity->get($fieldName)->value);
      }

      // Now we'll check the other fields
      if(!in_array($fieldName, $ignore_fields) && !in_array($fieldName, $manual_fields))
      {
        $fieldNamePretty = $fieldToPrettyMapping[$fieldName];
        switch ($fieldDefinition->getType())
        {
          case 'geolocation':
            $attribute = null;
            if(!empty($this->entity->get($fieldName)->lat))
            {
              $attribute = new \stdClass();
              $attribute->lat = (float) $this->entity->get($fieldName)->lat;
              $attribute->lng = (float) $this->entity->get($fieldName)->lng;
            }
            $node->addAttribute($fieldNamePretty, $attribute);
            break;
          case 'entity_reference':
            // this is really hacky, we must consider finding a more performant solution that the one with the target_ids now
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

              $node->addRelationship($fieldNamePretty, $relationshipDataNode);
            }
            break;
          case 'image':
            $attribute = new \stdClass();
            if(!empty($this->entity->get($fieldName)->entity))
            {
              $attribute->width = $this->entity->get($fieldName)->width;
              $attribute->height = $this->entity->get($fieldName)->height;
              $attribute->alt = $this->entity->get($fieldName)->alt;
              $attribute->title = $this->entity->get($fieldName)->title;
              $attribute->url = $this->entity->get($fieldName)->entity->url();

              $attribute->filename = $this->entity->get($fieldName)->entity->get('filename')->value;
              $attribute->uri = $this->entity->get($fieldName)->entity->get('uri')->value;
              $attribute->filemime = $this->entity->get($fieldName)->entity->get('filemime')->value;
              $attribute->filesize = $this->entity->get($fieldName)->entity->get('filesize')->value;

              $node->addAttribute($fieldNamePretty, $attribute);
            }
            else
            {
              $node->addAttribute($fieldNamePretty, null);
            }
            break;
          case 'address':
            $address = $this->entity->get($fieldName);
            $attribute = null;
            if(!empty($address->country_code))
            {
              $attribute = new \stdClass();
              $attribute->country_code = $address->country_code;
              $attribute->administrative_area = $address->administrative_area;
              $attribute->locality = $address->locality;
              $attribute->dependent_locality = $address->dependent_locality;
              $attribute->postal_code = $address->postal_code;
              $attribute->sorting_code = $address->sorting_code;
              $attribute->address_line1 = $address->address_line1;
              $attribute->address_line2 = $address->address_line2;

            }
            $node->addAttribute($fieldNamePretty, $attribute);
            break;
          case 'created':
          case 'changed':
            // for some reason, created and changed aren't regular datetimes, they are unix timestamps in the database
            $timestamp = $this->entity->get($fieldName)->value;
            $datetime = \DateTime::createFromFormat('U', $timestamp);
            $node->addAttribute($fieldNamePretty, $datetime->format( 'c' ));
            break;
          default:
            $node->addAttribute($fieldNamePretty, $this->entity->get($fieldName)->value);
            break;
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

  // This function returns a mapping of the different fields, with "field_" stripped, and a dasherized representation of the field name
  public static function getPrettyFieldsToFieldsMapping()
  {
    $mapping = array();
    $fieldList = static::getFieldDefinitions();

    foreach($fieldList as $key => $value)
    {
      $fieldnamepretty = StringUtils::dasherize(str_replace('field_', '', $key));
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
}
