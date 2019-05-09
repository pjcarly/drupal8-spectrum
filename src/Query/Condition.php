<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Exceptions\InvalidOperatorException;

/**
 * This class provides functionality to create Conditions. These can be added to Queries, ConditionGroups or other places
 */
class Condition
{
  /**
   * The name of the drupal field
   *
   * @var string
   */
  public $fieldName;

  /**
   * The operator that is being used in the condition
   *
   * @var string
   */
  public $operator;

  /**
   * The value you want to filter by, in case the string 'null' is passed, it will be replaced by NULL
   *
   * @var string|int|float|bool|array
   */
  public $value;

  /**
   * This array contains the allowed single value operators
   *
   * @var array
   */
  public static $singleValueOperators = ['=', '<>', '>', '>=', '<', '<=', 'LIKE', 'CONTAINS', 'STARTS_WITH', 'ENDS_WITH'];

  /**
   * This array contains the allowed multi value operators
   *
   * @var array
   */
  public static $multipleValueOperators = ['IN', 'NOT IN', 'BETWEEN'];

  /**
   *
   * @param string $fieldName The Drupal field name
   * @param string $operator The operator you want to use
   * @param mixed $value The value of your condition, can be any type, Pass the string 'null' to check for NULL
   */
  public function __construct(string $fieldName, string $operator, $value)
  {
    $this->fieldName = $fieldName;
    $this->operator = $operator;
    $this->value = $value;
  }

  /**
   * Validates the Condition, if the value is an array, multi operators can be used, if it is something else, single values can be used
   *
   * @return Condition
   */
  public function validateValues() : Condition
  {
    if(is_array($this->value) && !Condition::isValidMultipleModelsOperator($this->operator))
    {
      throw new InvalidOperatorException();
    }
    else if(!is_array($this->value) && !Condition::isValidSingleModelOperator($this->operator))
    {
      throw new InvalidOperatorException();
    }

    return $this;
  }

  /**
   * Apply this condition to a DrupalEntityQuery. This can both be a drupal query or a condition (in order to create nested groups)
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface|\Drupal\Core\Entity\Query\ConditionInterface $query
   * @return Condition
   */
  public function addQueryCondition($query) : Condition
  {
    if($this->value === 'null')
    {
      if($this->operator === '<>')
      {
        $query->exists($this->fieldName);
      }
      else
      {
        $query->notExists($this->fieldName);
      }

    }
    else
    {
      if($this->operator === '<>' || $this->operator === 'NOT IN')
      {
        // Workaround for Drupal's lack of support for LEFT JOIN (else you would miss empty values)
        $orGroup = $query->orConditionGroup();
        $orGroup->condition($this->fieldName, $this->value, $this->operator);
        $orGroup->notExists($this->fieldName);
        $query->condition($orGroup);
      }
      else
      {
        $query->condition($this->fieldName, $this->value, $this->operator);
      }
    }

    return $this;
  }

  /**
   * Checks if the provided string is a valid single value operator
   *
   * @param string $operator
   * @return boolean
   */
  public static function isValidSingleModelOperator(string $operator) : bool
  {
    return in_array(strtoupper($operator), Condition::$singleValueOperators);
  }

  /**
   * Checks if the provided string is a valid multi value operator
   *
   * @param string $operator
   * @return boolean
   */
  public static function isValidMultipleModelsOperator(string $operator) : bool
  {
    return in_array(strtoupper($operator), Condition::$multipleValueOperators);
  }

  /**
   * Returns the fieldName of the condition
   *
   * @return string
   */
  public function getFieldName() : string
  {
    return $this->fieldName;
  }

  /**
   * Returns the operator of the Condition
   *
   * @return string
   */
  public function getOperator() : string
  {
    return $this->operator;
  }

  /**
   * Returns the Value of the condition
   *
   * @return string|int|float|bool|array
   */
  public function getValue()
  {
    return $this->value;
  }
}
