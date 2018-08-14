<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

use Drupal\spectrum\Query\Condition as QueryCondition;
use Drupal\spectrum\Query\ConditionGroup as QueryConditionGroup;

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
  public function buildQueryConditionGroup(): QueryConditionGroup
  {
    // This will be the returnvalue, we will fill in the conditions below
    $conditiongroup = new QueryConditionGroup();

    // We start of with field we are querying on
    $field = $this->entity->field_field->value;

    // Next lets check what type of operator it is, in case it is multivalue, we will have to explode the value later on
    $operator = static::$operationMapping[$this->entity->field_operator->value];

    // Next we get the value from the model, and see if we need parse the value
    $values = [$this->entity->field_value->value];
    // In case the operator is a multivalue field, we explode the value and parse every single value at a time
    if(in_array($operator, QueryCondition::$multipleValueOperators))
    {
      $values = explode(',', $value);
      $values = array_map('trim', $value);
    }

    // Now we loop over every found value, and add conditions to the conditiongroup if necessary
    $fieldDefinition = null;
    foreach($values as $value)
    {
      // We will parse possible literals in the value
      if(in_array($value, static::$userLiterals) || in_array($value, static::$dateLiterals))
      {
        $fieldDefinition = empty($fieldDefinition) ? $this->getDrupalFieldDefinition() : $fieldDefinition;
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

                $conditiongroup->addCondition(new QueryCondition($field, $operator, $value));
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
    }


    new QueryCondition($field, $operator, $value);
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
