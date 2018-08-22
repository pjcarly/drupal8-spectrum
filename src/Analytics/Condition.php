<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

use Drupal\spectrum\Query\Condition as QueryCondition;

class Condition extends Model
{
  /**
   * These are the values that can be translated in a condtion for an entity_reference field to the User entity
   *
   * @var array
   */
  public static $userLiterals = ['MYSELF'];

  /**
   * These are values that can be translated into conditions for a field of type Date
   *
   * @var array
   */
  public static $dateLiterals = ['TODAY', 'NOW'];

  /**
   * These provide a mapping between the select list values, and the actual operator in the query
   *
   * @var array
   */
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

  /**
   * The entityType for this model
   *
   * @var string
   */
  public static $entityType = 'query';

  /**
   * The Bundle for this Model
   *
   * @var string
   */
  public static $bundle = 'condition';

  /**
   * The relationships to other Models
   *
   * @return void
   */
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

  /**
   * Get a Drupal Field Definition for the field in the condition
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   */
  private function getDrupalFieldDefinition()
  {
    $fieldName = $this->entity->field_field->value;
    $fieldDefinitions = $this->get('parent')->getDrupalFieldDefinitions();

    if(array_key_exists($fieldName, $fieldDefinitions))
    {
      return $fieldDefinitions[$fieldName];
    }

    return null;
  }
}
