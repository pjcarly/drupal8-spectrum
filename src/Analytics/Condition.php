<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

use Drupal\spectrum\Query\Condition as QueryCondition;

class Condition extends Model
{
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

  public function buildQueryCondition() : QueryCondition
  {
    $field = $this->entity->field_field->value;
    $operator = static::$operationMapping[$this->entity->field_operator->value];
    $value = $this->entity->field_value->value;

    if(in_array($operator, QueryCondition::$multipleValueOperators))
    {
      $value = explode(',', $value);
      $value = array_map('trim', $value);
    }

    return new QueryCondition($field, $operator, $value);
  }
}
