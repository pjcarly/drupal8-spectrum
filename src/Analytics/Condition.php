<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

use Drupal\spectrum\Query\Condition as QueryCondition;

class Condition extends Model
{
  public static $userLiterals = ['MYSELF'];
  public static $dateLiterals = ['TODAY', 'NOW'];

  public static $operationMapping = [
    'EQUALS' => '=',
    'NOT_EQUALS' => '<>',
    'GREATER_THAN' => '>',
    'GREATER_OR_EQUAL' => '>=',
    'LESS_THAN' => '<',
    'LESS_OR_EQUAL' => '<=',
    'LIKE' => 'LIKE',
    'CONTAINS' => 'CONTAINS',
    'STARTS_WITH' => 'STARTS_WITH',
    'ENDS_WITH' => 'ENDS_WITH',
    'IN' => 'IN',
    'NOT_IN' => 'NOT IN',
    'BETWEEN' => 'BETWEEN',
  ];
  public static $entityType = 'query';
  public static $bundle = 'condition';
  public static $idField = 'id';

  public static $plural = 'Conditions';

  public static function relationships()
  {
    static::addRelationship(new FieldRelationship('parent', 'field_parent.target_id'));
  }

  /**
   * Returns the Spectrum Condition that can be used in a Query
   *
   * @return QueryCondition
   */
  public function buildQueryCondition(): QueryCondition
  {
    $field = $this->entity->field_field->value;
    $operator = static::$operationMapping[$this->entity->field_operator->value];
    $value = $this->getValue(); // This parses possible literals

    if(in_array($operator, QueryCondition::$multipleValueOperators))
    {
      $value = explode(',', $value);
      $value = array_map('trim', $value);
    }

    return new QueryCondition($field, $operator, $value);
  }

  /**
   * Returns the value the Condition will contain
   *
   * @return void
   */
  public function getValue()
  {
    $value = $this->entity->field_value->value;

    if(in_array($value, static::$userLiterals) || in_array($value, static::$dateLiterals))
    {
      $fieldDefinition = $this->getDrupalFieldDefinition();
      if(!empty($fieldDefinition))
      {
        $fieldType = $fieldDefinition->getType();
        if($fieldType === 'entity_reference')
        {
          $fieldSettings = $fieldDefinition->getItemDefinition()->getSettings();

          if($fieldSettings['target_type'] === 'user')
          {
            if($value === 'MYSELF')
            {
              $currentUser = \Drupal::currentUser();
              $value = $currentUser->id();
            }
          }
        }
        else if($fieldType === 'datetime')
        {
          if($value === 'TODAY')
          {
            $today = new \DateTime();
            $value = $today->format(DATETIME_DATE_STORAGE_FORMAT);
          }
          else if($value === 'NOW')
          {
            $now = new \DateTime();
            $value = $now->format(DATETIME_DATETIME_STORAGE_FORMAT);
          }
        }
      }
    }

    return $value;
  }

  private function getDrupalFieldDefinition()
  {
    $fieldName = $this->entity->field_field->value;
    $fieldDefinitions = $this->parent->getDrupalFieldDefinitions();

    if(array_key_exists($fieldName, $fieldDefinitions))
    {
      return $fieldDefinitions[$fieldName];
    }

    return null;
  }
}
