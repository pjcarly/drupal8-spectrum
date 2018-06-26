<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Utils\ParenthesisParser;
use Drupal\spectrum\Exceptions\InvalidOperatorException;
use Drupal\spectrum\Exceptions\InvalidQueryException;
use Drupal\Core\Entity\Query\QueryInterface;

class ConditionGroup
{
  // Condition groups are used to bundle logic (AND(1,2, OR(3,4))) with a list of conditions
  private $conditions;
  private $logic;

  public function __construct()
  {
    $this->conditions = [];
  }

  public function addCondition(Condition $condition) : ConditionGroup
  {
    $this->conditions[] = $condition;
    return $this;
  }

  public function getConditions() : array
  {
    return $this->conditions;
  }

  public function setLogic(string $logic) : ConditionGroup
  {
    $this->logic = $logic;
    return $this;
  }

  public function applyConditionsOnQuery(QueryInterface $query) : QueryInterface
  {
    if(empty($this->logic))
    {
      throw new InvalidQueryException('No Condition logic passed for Condition Group');
    }

    $parser = new ParenthesisParser();
    $structure = $parser->parse($this->logic);

    $this->setConditionsOnBase($query, $structure, $query);
    return $query;
  }

  private function setConditionsOnBase($base, $logic, $drupalQuery) : void
  {
    $conditionGroup;
    foreach($logic as $key => $value)
    {
      if(is_array($value))
      {
        if(empty($conditionGroup))
        {
          throw new InvalidQueryException();
        }
        else
        {
          $this->setConditionsOnBase($conditionGroup, $value, $drupalQuery);
        }
      }
      else if(strtoupper($value) === 'OR')
      {
        if(!empty($conditionGroup))
        {
          $base->condition($conditionGroup);
        }

        $conditionGroup = $drupalQuery->orConditionGroup();
      }
      else if(strtoupper($value) === 'AND')
      {
        if(!empty($conditionGroup))
        {
          $base->condition($conditionGroup);
        }

        $conditionGroup = $drupalQuery->andConditionGroup();
      }
      else if(is_numeric($value))
      {
        // check for condition in list
        if(array_key_exists($value-1, $this->conditions))
        {
          $condition = $this->conditions[$value-1];
          $condition->addQueryCondition($base);
        }
        else
        {
          // Condition doesnt exist, ignore it
        }
      }
    }

    if(!empty($conditionGroup))
    {
      $base->condition($conditionGroup);
    }
  }
}
