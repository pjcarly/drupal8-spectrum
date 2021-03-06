<?php

namespace Drupal\spectrum\Serializer;

use Drupal\address\Plugin\Field\FieldType\AddressFieldItemList;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiNode;
use Drupal\spectrum\Serializer\JsonApiDataNode;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Utils\StringUtils;

use Drupal\spectrum\Models\File;
use Drupal\spectrum\Models\Image;
use Drupal\spectrum\Models\User;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * This trait contains all the methods used for serializing a Model to a jsonapi.org compliant document
 */
trait ModelSerializerMixin
{
  protected static $prettyFieldsMappingIndex = [];
  protected static $fieldsMappingIndex = [];
  /**
   * Returns an array of fields that will be ignored during serialization. These are mostly internal drupal fields that shouldnt be exposed, as we dont want to leak configuration
   *
   * @return array
   */
  public static function getIgnoreFields(): array
  {
    return [
      'type',
      'revision_log',
      'vid',
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'revision_translation_affected',
      'revision_translation_affected',
      'revision_default',
      'revision_id',
      'revision_created',
      'path',
      'default_langcode',
      'content_translation_source',
      'content_translation_outdated',
      'content_translation_created',
      'content_translation_changed',
      'content_translation_uid',
      'content_translation_status',
      'preferred_langcode',
      'preferred_admin_langcode',
      'langcode',
      'pass',
      'uuid',
      'metatag',
      'field_meta_tags',
      'menu_link',
      'roles',
      'reusable',
      'rh_action',
      'rh_redirect',
      'rh_redirect_response',
      'behavior_settings'
    ];
  }

  /**
   * Returns the value that will be serialized for the passed fieldname on this entity
   * Necessary transforms will be done based on the field type
   *
   * @param string $fieldName
   * @param FieldDefinitionInterface $fieldDefinition
   * @return mixed
   */
  public function getValueToSerialize(string $fieldName, FieldDefinitionInterface $fieldDefinition = null)
  {
    $fieldDefinition = empty($fieldDefinition) ? static::getFieldDefinition($fieldName) : $fieldDefinition;
    $valueToSerialize = null;
    $fieldType = $fieldDefinition->getType();
    $fieldCardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();

    switch ($fieldType) {
      case 'address':
        /** @var AddressFieldItemList $address */
        $address = $this->entity->{$fieldName};
        $attribute = null;
        if (!empty($address->country_code)) {
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
        $autonumberValue = $this->entity->{$fieldName}->value;
        $valueToSerialize = null;

        if (isset($autonumberValue)) {
          $valueToSerialize = (int) $autonumberValue;
        }
        break;
      case 'boolean':
        $valueToSerialize = ($this->entity->{$fieldName}->value == true); // must be none-stricly typed, as drupal sometimes uses true or false, and sometimes '1' and '0'
        break;
      case 'changed':
      case 'created':
      case 'timestamp':
        // for some reason, created and changed aren't regular datetimes, they are unix timestamps in the database
        $timestamp = $this->entity->{$fieldName}->value;
        $datetime = \DateTime::createFromFormat('U', $timestamp);
        $valueToSerialize = $datetime->format('c');
        break;
      case 'datetime':
        $dateValue = null;
        $attributeValue = $this->entity->{$fieldName}->value;

        if (!empty($attributeValue)) {
          // We must figure out if this is a Date field or a datetime field
          // lets get the meta information of the field
          $fieldSettingsDatetimeType = $fieldDefinition->getItemDefinition()->getSettings()['datetime_type'];
          if ($fieldSettingsDatetimeType === 'date') {
            $dateValue = new \DateTime($attributeValue);
            $dateValue = $dateValue->format('Y-m-d');
          } else if ($fieldSettingsDatetimeType === 'datetime') {
            $dateValue = new \DateTime($attributeValue);
            $dateValue = $dateValue->format('Y-m-d\TH:i:s') . '+00:00'; // we are returning as UTC
          }
        }

        $valueToSerialize = $dateValue;
        break;
      case 'decimal':
        $valueToSerialize = $this->entity->{$fieldName}->value;
        $valueToSerialize = $valueToSerialize === NULL ? NULL : (float) $valueToSerialize;
        break;
      case 'entity_reference':
      case 'entity_reference_revisions':
        // TODO: this is really hacky, we must consider finding a more performant solution than the one with the target_ids now
        if (!empty($this->entity->{$fieldName}->entity)) {
          // Lets figure out what the target-type is, in some cases, we just serialize the target_id (currency for example)
          // Becaue it is a ISO default
          $fieldObjectSettings = $fieldDefinition->getSettings();

          // Lets prepare a data node
          $relationshipDataNode = new JsonApiDataNode();

          // Lets also check the cardinality of the field (amount of references the field can contain)
          // If it is more than 1 item (or -1 in case of unlimited references), we must return an array
          if ($fieldCardinality !== 1) {
            $relationshipDataNode->asArray(true);
          }

          $idsThatHaveBeenset = [];
          foreach ($this->entity->{$fieldName} as $referencedEntity) {
            $target_id = $referencedEntity->target_id;
            $targetEntity = $referencedEntity->entity;

            // 1) We only set references once, here we make sure an id hasnt already been set
            //    This can happen when the record was added twice to the entity reference field
            // 2) We must also be careful, Drupal doesnt cleanup references in target_id fields
            //    So we must also check whether the entity exists before we can add the target_id
            //    In case it has been deleted
            if (!array_key_exists($target_id, $idsThatHaveBeenset) && !empty($targetEntity)) {
              $idsThatHaveBeenset[$target_id] = $target_id;
              $relationshipNode = new JsonApiNode();
              $relationshipNode->setId($referencedEntity->target_id);

              // Lets see if we have a modelclass to get the type from
              $targetBundle = $targetEntity->bundle();
              $targetEntityType = $targetEntity->getEntityType()->id();

              if (Model::hasModelClassForEntityAndBundle($targetEntityType, $targetBundle)) {
                $targetModelClass = Model::getModelClassForEntityAndBundle($targetEntityType, $targetBundle);

                $relationshipNode->setType($targetModelClass::getSerializationType());
              } else {
                // nothing found. Lets use the bundle
                $relationshipNode->setType($targetEntity->bundle());
              }

              $relationshipDataNode->addNode($relationshipNode);
            }
          }

          if (!empty($fieldObjectSettings) && array_key_exists('target_type', $fieldObjectSettings) && $fieldObjectSettings['target_type'] === 'currency') {
            $valueToSerialize = $referencedEntity->target_id;
          } else {
            $valueToSerialize = $relationshipDataNode;
          }
        }
        break;
      case 'file':
        if (!empty($this->entity->{$fieldName}->entity)) {
          $fileModel = new File($this->entity->{$fieldName}->entity); // TODO File/Image model fix
          $jsonapinode = $fileModel->getJsonApiNode();

          $attribute = new \stdClass();
          $attribute->id = $jsonapinode->getId();
          $attribute->filename = $jsonapinode->getAttribute('filename');
          $attribute->url = $jsonapinode->getAttribute('url');
          $attribute->filemime = $jsonapinode->getAttribute('filemime');
          $attribute->filesize = $jsonapinode->getAttribute('filesize');
          $attribute->hash = $jsonapinode->getAttribute('hash');

          // TODO: add URL like image

          $valueToSerialize = $attribute;
        } else {
          $valueToSerialize = null;
        }
        break;
      case 'geolocation':
        $attribute = null;
        if (!empty($this->entity->{$fieldName}->lat)) {
          $attribute = new \stdClass();
          $attribute->lat = (float) $this->entity->{$fieldName}->lat;
          $attribute->lng = (float) $this->entity->{$fieldName}->lng;
        }
        $valueToSerialize = $attribute;
        break;
      case 'integer':
        $fieldValue = $this->entity->{$fieldName}->value;

        if ($fieldValue === NULL) {
          $valueToSerialize = NULL;
        } else {
          $valueToSerialize = (int) $fieldValue;
        }

        break;
      case 'image':
        if ($fieldCardinality !== 1) {
          $valueToSerialize = [];

          foreach ($this->entity->{$fieldName} as $fieldValue) {
            if (!empty($fieldValue->entity)) {
              $imageModel = Image::forgeByEntity($fieldValue->entity);
              $jsonapinode = $imageModel->getJsonApiNode();

              $attribute = new \stdClass();
              $attribute->id = $jsonapinode->getId();
              $attribute->filename = $jsonapinode->getAttribute('filename');
              $attribute->url = $jsonapinode->getAttribute('url');
              $attribute->filemime = $jsonapinode->getAttribute('filemime');
              $attribute->filesize = $jsonapinode->getAttribute('filesize');
              $attribute->hash = $jsonapinode->getAttribute('hash');

              $attribute->width = $fieldValue->width;
              $attribute->height = $fieldValue->height;
              $attribute->alt = $fieldValue->alt;
              $attribute->title = $fieldValue->title;

              $valueToSerialize[] = $attribute;
            }
          }
        } else {
          if (!empty($this->entity->{$fieldName}->entity)) {
            $imageModel = Image::forgeByEntity($this->entity->{$fieldName}->entity);
            $jsonapinode = $imageModel->getJsonApiNode();

            $attribute = new \stdClass();
            $attribute->id = $jsonapinode->getId();
            $attribute->filename = $jsonapinode->getAttribute('filename');
            $attribute->url = $jsonapinode->getAttribute('url');
            $attribute->filemime = $jsonapinode->getAttribute('filemime');
            $attribute->filesize = $jsonapinode->getAttribute('filesize');
            $attribute->hash = $jsonapinode->getAttribute('hash');

            $attribute->width = $this->entity->{$fieldName}->width;
            $attribute->height = $this->entity->{$fieldName}->height;
            $attribute->alt = $this->entity->{$fieldName}->alt;
            $attribute->title = $this->entity->{$fieldName}->title;

            $valueToSerialize = $attribute;
          } else {
            $valueToSerialize = null;
          }
        }

        break;
      case 'json':
        $valueToSerialize = json_decode($this->entity->{$fieldName}->value);
        break;
      case 'link':
        $valueToSerialize = $this->entity->{$fieldName}->uri;
        break;
      case 'metatag':
        $meta = $this->entity->{$fieldName}->value;
        if (!empty($meta)) {
          $meta = unserialize($meta);
          $value = new \stdClass();
          // Now we render the tokens
          $token = \Drupal::service('token');
          $data = [
            'node' => $this->entity
          ];
          // Loop over all meta information
          foreach ($meta as $key => $metaValue) {
            if (is_string($metaValue)) {
              $value->$key = strip_tags($token->replace($metaValue, $data));
            }
          }
        }

        $valueToSerialize = $value;
        break;
      case 'uri':
        // ignore for now, this is used in the deserialization of the file entity, we choose not to return it for now
        //$valueToSerialize = $this->entity->{$fieldName}->value;
        //$node->addAttribute('url', file_create_url($this->entity->{$fieldName}->value));
        break;
      default:
        if ($fieldCardinality !== 1) {
          // More than 1 value allowed in the field
          $value = [];
          $fieldValues = $this->entity->{$fieldName};
          foreach ($fieldValues as $fieldValue) {
            $value[] = $fieldValue->value;
          }
        } else {
          $value = $this->entity->{$fieldName}->value;
        }

        $valueToSerialize = $value;
        break;
    }

    return $valueToSerialize;
  }

  protected function serializeField(string $fieldName, string $fieldNamePretty, FieldDefinitionInterface $fieldDefinition, JsonApiNode $node)
  {
    $fieldType = $fieldDefinition->getType();
    $fieldObjectSettings = $fieldDefinition->getSettings();

    $valueToSerialize = $this->getValueToSerialize($fieldName, $fieldDefinition);

    if ($fieldType === 'entity_reference' || $fieldType === 'entity_reference_revisions') {
      if (!empty($fieldObjectSettings) && array_key_exists('target_type', $fieldObjectSettings) && $fieldObjectSettings['target_type'] === 'currency') {
        $node->addAttribute($fieldNamePretty, $valueToSerialize);
      } else {
        $node->addRelationship($fieldNamePretty, $valueToSerialize);
      }
    } else {
      $node->addAttribute($fieldNamePretty, $valueToSerialize);
    }
  }

  /**
   * This function will return a JsonApiNode representation of the current model.
   * Necessary checks will be done to make sure the user has access to the fields he wants to serialize.
   * If the user doesnt have access, fields will omitted from the JsonApiNode
   *
   * @return JsonApiNode
   */
  public function getJsonApiNode(): JsonApiNode
  {
    $node = new JsonApiNode();

    $ignoreFields = static::getIgnoreFields();
    $fieldToPrettyMapping = static::getFieldsToPrettyFieldsMapping();
    $fieldDefinitions = static::getFieldDefinitions();

    foreach ($fieldDefinitions as $fieldName => $fieldDefinition) {
      // First let's check the manual fields
      if ($fieldName === 'type') {
        // Disabled for now, we use the type of the model
        //$node->setType(StringUtils::dasherize($this->entity->{$fieldName}->target_id));
        continue;
      } else if ($fieldName === static::getIdField()) {
        $node->setId($this->entity->{$fieldName}->value);
        continue;
      }

      // Now we'll check the other fields
      if (!in_array($fieldName, $ignoreFields) && static::currentUserHasFieldPermission($fieldName, 'view')) {
        $fieldNamePretty = $fieldToPrettyMapping[$fieldName];
        $this->serializeField($fieldName, $fieldNamePretty, $fieldDefinition, $node);
      }
    }

    // some entity types don't have a type field, we must rely on static definitions
    if (!$node->hasType()) {
      // some entity types don't have a bundle (user for example) so we must rely on the entity type itself
      $node->setType(static::getSerializationType());
    }

    return $node;
  }

  /**
   * Returns a serialized JsonApiRootNode
   *
   * @return \stdClass
   */
  public function serialize(): \stdClass
  {
    $root = new JsonApiRootNode();
    $node = $this->getJsonApiNode();
    $root->addNode($node);

    return $root->serialize();
  }

  /**
   * This function returns, the allowed columns where we can sort and filter on in the model api handler
   * Only certain types have extra columns, the first key of the array will be the type of field, the second key the pretty field, and the value will be the actual column name
   *
   * @return array
   */
  public static function getTypePrettyFieldToFieldsMapping(): array
  {
    $mapping = [];
    $mapping['address'] = [];
    $mapping['address']['country-code'] = 'country_code';
    $mapping['address']['administrative-area'] = 'administrative_area';
    $mapping['address']['locality'] = 'locality';
    $mapping['address']['dependent-locality'] = 'dependent_locality';
    $mapping['address']['postal-code'] = 'postal_code';
    $mapping['address']['sorting-code'] = 'sorting_code';
    $mapping['address']['address-line1'] = 'address_line1';
    $mapping['address']['address-line2'] = 'address_line2';
    return $mapping;
  }


  /**
   * This function returns a mapping of the different fields, with "field_" stripped, and a dasherized representation of the field name
   * Mainly to avoid exposing drupal configuration to jsonapi.org.
   *
   * @return array
   */
  public static function getPrettyFieldsToFieldsMapping(): array
  {
    $modelClassKey = static::getModelClassKey();

    if (!array_key_exists($modelClassKey, static::$prettyFieldsMappingIndex)) {
      $mapping = [];
      $fieldList = static::getFieldDefinitions();

      foreach ($fieldList as $key => $value) {
        if ($key !== 'title') {
          $fieldnamepretty = trim(trim(StringUtils::dasherize(str_replace('field_', '', $key)), '-'));
        } else {
          $fieldnamepretty = 'name';
        }

        $mapping[$fieldnamepretty] = $key;
      }

      static::$prettyFieldsMappingIndex[$modelClassKey] = $mapping;
    }

    return static::$prettyFieldsMappingIndex[$modelClassKey];
  }

  /**
   * This function returns the inverse of getPrettyFieldsToFieldsMapping(), for mapping pretty fields back to the original
   *
   * @return void
   */
  public static function getFieldsToPrettyFieldsMapping(): array
  {
    $modelClassKey = static::getModelClassKey();

    if (!array_key_exists($modelClassKey, static::$fieldsMappingIndex)) {
      $prettyMapping = static::getPrettyFieldsToFieldsMapping();
      $mapping = array_flip($prettyMapping);

      static::$fieldsMappingIndex[$modelClassKey] = $mapping;
    }


    return static::$fieldsMappingIndex[$modelClassKey];
  }

  /**
   * Pass in a pretty field name, and have the drupal field name returned if not found, null will be returned
   *
   * @param string $prettyField
   * @return string|null
   */
  public static function getFieldForPrettyField(string $prettyField): ?string
  {
    $field = null;
    $prettyToFieldsMap = static::getPrettyFieldsToFieldsMapping();

    if (array_key_exists($prettyField, $prettyToFieldsMap)) {
      $field = $prettyToFieldsMap[$prettyField];
    }

    return $field;
  }

  /**
   * Returns the pretty field for a drupal field
   *
   * @param string $field
   * @return string|null
   */
  public static function getPrettyFieldForField(string $field): ?string
  {
    $prettymapping = static::getFieldsToPrettyFieldsMapping();
    $prettyField = null;

    if (array_key_exists($field, $prettymapping)) {
      $prettyField = $prettymapping[$field];
    }

    return $prettyField;
  }

  /**
   * Checks if a pretty field exists for this entity
   *
   * @param string $prettyField
   * @return boolean
   */
  public static function prettyFieldExists(string $prettyField): bool
  {
    $field = static::getFieldForPrettyField($prettyField);
    return !empty($field);
  }

  /**
   * Checks whether the logged in user has access to a certain field on this Model
   *
   * @param string $field The field on the model (for example "field_body")
   * @param string $access What type of access ("view" or "edit")
   * @return boolean
   */
  public static function currentUserHasFieldPermission(
    string $field,
    string $access
  ): bool {
    return User::loggedInUser()
      ->hasFieldPermission(get_called_class(), $field, $access);
  }
}
