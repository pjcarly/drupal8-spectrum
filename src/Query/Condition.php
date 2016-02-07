<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Exceptions\InvalidOperatorException;

class Condition
{
	public $fieldName;
	public $operator;
	public $value;

	public static $singleValueOperators = array('=', '<>', '>', '>=', '<', '<=', 'STARTS_WITH', 'CONTAINS');
	public static $multipleValueOperators = array('IN', 'NOT IN');

	public function __construct($fieldName, $operator, $value)
	{
		$this->fieldName = $fieldName;
		$this->operator = $operator;
		$this->value = $value;
	}

	public function validateValues()
	{
		if(is_array($this->value) && !in_array($this->operator, Condition::$multipleValueOperators))
		{
			throw new InvalidOperatorException();
		}
		else if(!is_array($this->value) && !in_array($this->operator, Condition::$singleValueOperators))
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
}
