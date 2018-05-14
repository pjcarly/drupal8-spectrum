<?php

namespace Drupal\spectrum\Serializer;

use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\JsonApiNode;
use Drupal\spectrum\Serializer\JsonApiBaseNode;
use Drupal\spectrum\Serializer\JsonApiDataNode;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Collection;
Use Drupal\spectrum\Utils\StringUtils;

use Drupal\spectrum\Models\File;
use Drupal\spectrum\Models\Image;

trait ModelSQLHelperMixin
{
  public static function getViewTabelColumnForField(string $fieldName, string $alias, $fieldDefinition = null) : array
  {
    $fieldDefinition = empty($fieldDefinition) ? static::getFieldDefinition($fieldName) : $fieldDefinition;
    $fieldType = $fieldDefinition->getType();
    $fieldCardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();


    $columns = [];

    if($fieldCardinality > 1)
    {
      // Higher field cardinality not supported
      return $columns;
    }

    $columnBase = '`'.$alias.'`.'.$fieldName.'_';

    switch ($fieldType)
    {
      case 'address':
          $columns[] = $columnBase.'country_code AS '.$alias.'_country_code';
          $columns[] = $columnBase.'administrative_area AS '.$alias.'_administrative_area';
          $columns[] = $columnBase.'locality AS '.$alias.'_locality';
          $columns[] = $columnBase.'dependent_locality AS '.$alias.'_dependent_locality';
          $columns[] = $columnBase.'postal_code AS '.$alias.'_postal_code';
          $columns[] = $columnBase.'sorting_code AS '.$alias.'_sorting_code';
          $columns[] = $columnBase.'address_line1 AS '.$alias.'_address_line1';
          $columns[] = $columnBase.'address_line2 AS '.$alias.'_address_line2';
        break;
      case 'changed':
      case 'created':
      case 'timestamp':
        // for some reason, created and changed aren't regular datetimes, they are unix timestamps in the database

        break;
      case 'entity_reference':
      case 'file':
      case 'image':
        $columns[] = $columnBase.'target_id AS `'.$alias.'`';
        break;
      case 'geolocation':
        $columns[] = $columnBase.'lat AS '.$alias.'_lat';
        $columns[] = $columnBase.'lng AS '.$alias.'_lng';
        break;
      case 'json':
      case 'uri':

      break;
      case 'link':
        $columns[] = $columnBase.'uri AS `'.$alias.'`';
        break;
      default:
        $columns[] = $columnBase.'value AS `'.$alias.'`';
        break;
    }

    return $columns;
  }

  public static function getViewSelectColumns() : array
  {
    $columns = [];

    $ignoreFields = static::getIgnoreFields();
    $fieldDefinitions = static::getFieldDefinitions();
    $fieldToPrettyMapping = static::getFieldsToPrettyFieldsMapping();

    foreach($fieldDefinitions as $fieldName => $fieldDefinition)
    {
      $fieldNamePretty = $fieldToPrettyMapping[$fieldName];
      $fieldNamePretty = StringUtils::underscore($fieldNamePretty);

      if($fieldName === 'type')
      {
        continue;
      }
      else if($fieldName === static::$idField)
      {
        $columns[] = static::$entityType.'.'.$fieldName.' AS id';
        continue;
      }

      // Now we'll check the other fields
      if(!in_array($fieldName, $ignoreFields))
      {
        $type = substr($fieldName, 0, 6);

        if($type === 'field_')
        {
          $fieldColumns = static::getViewTabelColumnForField($fieldName, $fieldNamePretty, $fieldDefinition);
          $columns = array_merge($columns, $fieldColumns);
        }
        else if($fieldName === 'user_picture') // doesnt follow the defaults for some reason
        {
          $columns[] = 'user_picture.user_picture_target_id AS user_picture';
        }
        else
        {
          $columns[] = static::$entityType.'.'.$fieldName.' AS `'.$fieldNamePretty.'`';
        }
      }
    }

    return $columns;
  }

  public static function getViewBaseTable() : string
  {
    $baseTablePrefix = static::$entityType;

    if($baseTablePrefix === 'user')
    {
      $baseTablePrefix = 'users';
    }

    return $baseTablePrefix . '_field_data AS '.static::$entityType;
  }

  public static function getViewJoins() : array
  {
    $joins = [];

    $ignoreFields = static::getIgnoreFields();
    $fieldDefinitions = static::getFieldDefinitions();
    $fieldToPrettyMapping = static::getFieldsToPrettyFieldsMapping();

    foreach($fieldDefinitions as $fieldName => $fieldDefinition)
    {
      $fieldNamePretty = $fieldToPrettyMapping[$fieldName];
      $fieldNamePretty = StringUtils::underscore($fieldNamePretty);

      if($fieldName === 'type')
      {
        continue;
      }

      // Now we'll check the other fields
      if(!in_array($fieldName, $ignoreFields))
      {
        $type = substr($fieldName, 0, 6);

        if($type === 'field_')
        {
          $joins[] = 'LEFT JOIN '.static::$entityType.'__'.$fieldName. ' AS `'.$fieldNamePretty.'` ON `'.$fieldNamePretty.'`.entity_id = `'.static::$entityType.'`.'.static::$idField;
        }
        else if($fieldName === 'user_picture')
        {
          $joins[] = 'LEFT JOIN user__user_picture AS user_picture ON user_picture.entity_id = user.uid';
        }
      }
    }

    return $joins;
  }

  public static function getViewWhereClause() : ?string
  {
    return empty(static::$bundle) ? null : static::$entityType.'.type = \''. static::$bundle.'\'';
  }

  public static function getViewSelectQuery() : string
  {
    $query = 'SELECT '.implode(', ', static::getViewSelectColumns()). ' ';
    $query .= 'FROM '.static::getViewBaseTable().' ';
    $query .= implode(' ', static::getViewJoins()).' ';

    $where = static::getViewWhereClause();

    if(!empty($where))
    {
      $query .= 'WHERE '.$where;
    }

    return $query;
  }
}
