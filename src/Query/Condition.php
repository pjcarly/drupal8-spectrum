<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Entity\Query\ConditionInterface;
use Drupal\Core\Entity\Query\QueryInterface;
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
  public function validateValues(): Condition
  {
    if (is_array($this->value) && !Condition::isValidMultipleModelsOperator($this->operator)) {
      throw new InvalidOperatorException();
    } else if (!is_array($this->value) && !Condition::isValidSingleModelOperator($this->operator)) {
      throw new InvalidOperatorException();
    }

    return $this;
  }

  /**
   * Apply this condition to a DrupalEntityQuery. This can both be a drupal query or a condition (in order to create nested groups)
   *
   * @param QueryInterface|ConditionInterface $base where you want to put the condition on (can either be the base query, or another condition)
   * @param QueryInterface $query The base query (needed to create conditionGroups)
   * @return Condition
   */
  public function addQueryCondition($base, QueryInterface $query): Condition
  {
    if ($this->value === 'null') {
      if ($this->operator === '<>') {
        $base->exists($this->fieldName);
      } else {
        $base->notExists($this->fieldName);
      }
    } else {
      if ($this->operator === '<>' || $this->operator === 'NOT IN') {
        // Workaround for Drupal's lack of support for LEFT JOIN (else you would miss empty values)
        $orGroup = $query->orConditionGroup();
        $orGroup->condition($this->fieldName, $this->value, $this->operator);
        $orGroup->notExists($this->fieldName);
        $base->condition($orGroup);
      } else {
        $base->condition($this->fieldName, $this->value, $this->operator);
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
  public static function isValidSingleModelOperator(string $operator): bool
  {
    return in_array(strtoupper($operator), Condition::$singleValueOperators);
  }

  /**
   * Checks if the provided string is a valid multi value operator
   *
   * @param string $operator
   * @return boolean
   */
  public static function isValidMultipleModelsOperator(string $operator): bool
  {
    return in_array(strtoupper($operator), Condition::$multipleValueOperators);
  }

  /**
   * Returns the fieldName of the condition
   *
   * @return string
   */
  public function getFieldName(): string
  {
    return $this->fieldName;
  }

  /**
   * @param string $fieldName
   * @return self
   */
  public function setFieldName(string $fieldName): self
  {
    $this->fieldName = $fieldName;
    return $this;
  }

  /**
   * Returns the operator of the Condition
   *
   * @return string
   */
  public function getOperator(): string
  {
    return $this->operator;
  }

  /**
   * @param string $operator
   * @return self
   */
  public function setOperator(string $operator): self
  {
    $this->operator = $operator;
    return $this;
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

  /**
   * @param string|int|float|bool|array $value
   * @return self
   */
  public function setValue($value): self
  {
    $this->value = $value;
    return $this;
  }
}
