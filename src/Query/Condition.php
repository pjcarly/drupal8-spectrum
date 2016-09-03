<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Exceptions\InvalidOperatorException;

class Condition
{
	public $fieldName;
	public $operator;
	public $value;

	public static $singleValueOperators = array('=', '<>', '>', '>=', '<', '<=', 'LIKE', 'CONTAINS', 'STARTS_WITH', 'ENDS_WITH');
	public static $multipleValueOperators = array('IN', 'NOT IN');

	public function __construct($fieldName, $operator, $value)
	{
		$this->fieldName = $fieldName;
		$this->operator = $operator;
		$this->value = $value;
	}

	public function validateValues()
	{
		if(is_array($this->value) && !Condition::isValidMultipleModelsOperator($this->operator))
		{
			throw new InvalidOperatorException();
		}
		else if(!is_array($this->value) && !Condition::isValidSingleModelOperator($this->operator))
		{
			throw new InvalidOperatorException();
		}
	}

	public function addQueryCondition($query)
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
      $query->condition($this->fieldName, $this->value, $this->operator);
    }
	}

  public static function isValidSingleModelOperator($operator)
  {
    return in_array(strtoupper($operator), Condition::$singleValueOperators);
  }

  public static function isValidMultipleModelsOperator($operator)
  {
    return in_array(strtoupper($operator), Condition::$multipleValueOperators);
  }
}
