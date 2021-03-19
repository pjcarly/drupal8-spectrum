<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Utils\ParenthesisParser;
use Drupal\spectrum\Exceptions\InvalidQueryException;
use Drupal\Core\Entity\Query\QueryInterface;

/**
 * ConditionGroups are Groups of conditions that can have seperate logic for each condition in the group
 * Meaning you can add multiple conditions, and have AND and OR logic attached to it.
 * A condition group can be added to a Query, and will be added as an AND condition, with all the nested conditions correctly applied
 *
 * For Example:
 * $query = new EntityQuery('user');
 * $conditionGroup = new ConditionGroup();
 * $conditionGroup->addCondition(new Condition('name', '=', 'PJ'));
 * $conditionGroup->addCondition(new Condition('name', '=', 'Carly'));
 * $conditionGroup->setLogic('OR(1,2)');
 * $query->addConditionGroup($conditionGroup);
 * $query->addCondition(new Condition('status', '=', 1));
 * This will result in a (psuedo) query
 *
 * SELECT *
 * FROM user
 * WHERE (
 *  name = 'PJ'
 *  OR
 *  name = 'Carly'
 * )
 * AND
 * status = 1;
 */
class ConditionGroup
{
  /**
   * The different conditions in this ConditionGroup
   *
   * @var Condition[]|ConditionGroup[]
   */
  private $conditions;

  /**
   * This conjunction will be used when no logic was provided, this value can be either AND or OR
   *
   * @var string
   */
  private $defaultConjunction = 'AND';

  /**
   * The conditionLogic
   *
   * @var string
   */
  private $logic;

  public function __construct()
  {
    $this->conditions = [];
  }

  /**
   * Adds a Condition to the ConditionGroup, filter logic will be applied in the order you add conditions to the conditiongroup
   *
   * @param Condition $condition
   * @return self
   */
  public function addCondition(Condition $condition): self
  {
    $this->conditions[] = $condition;
    return $this;
  }


  /**
   * Adds a ConditionGroup to a ConditionGroup
   *
   * @param ConditionGroup $conditionGroup
   * @return self
   */
  public function addConditionGroup(ConditionGroup $conditionGroup): self
  {
    $this->conditions[] = $conditionGroup;
    return $this;
  }

  /**
   * Sets the default conjunction, the value can be either AND or OR
   *
   * @param string $value
   * @return self
   */
  public function setDefaultConjuntion(string $value): self
  {
    if ($value === 'AND' || $value === 'OR') {
      $this->defaultConjunction = $value;
    } else {
      throw new InvalidQueryException('The default conjunction can be either AND or OR');
    }

    return $this;
  }

  /**
   * Returns an array containing the conditions
   *
   * @return Condition[]|ConditionGroup[]
   */
  public function getConditions(): array
  {
    return $this->conditions;
  }

  /**
   * Removes the provided condition group from this conditiongroup
   *
   * @param ConditionGroup $conditionGroup
   * @return self
   */
  public function removeConditionGroup(ConditionGroup $conditionGroup): self
  {
    $key = array_search($conditionGroup, $this->conditions, true);

    if ($key !== false) {
      unset($this->conditions[$key]);
      $this->conditions = array_values($this->conditions);
    }

    return $this;
  }

  /**
   * Removes the provided condition from this conditiongroup
   *
   * @param Condition $condition
   * @return self
   */
  public function removeCondition(Condition $condition): self
  {
    $key = array_search($condition, $this->conditions, true);

    if ($key !== false) {
      unset($this->conditions[$key]);
      $this->conditions = array_values($this->conditions);
    }

    return $this;
  }

  /**
   * Sets the conditionlogic of this conditiongroup. The numbers in the logic
   * should correspond to the order in which you added conditions to the
   * conditiongroup.
   * You can reuse the same index multiple times, then the condition will be applied multiple times
   * For example 'AND(1,2,OR(1,3,4))'
   *
   * @param string $logic
   * @return self
   */
  public function setLogic(string $logic): self
  {
    $this->logic = $logic;
    return $this;
  }

  /**
   * Apply the conditions in the group on the provided Drupal Query
   *
   * @param QueryInterface $query
   * @return QueryInterface
   */
  public function applyConditionsOnQuery(QueryInterface $query, $base = null): QueryInterface
  {
    if (sizeof($this->conditions) === 0) {
      throw new InvalidQueryException('No conditions added to conditiongroup');
    }

    if (empty($this->logic)) {
      // no logic was found, lets join everything by the default conjunction
      $logic = strtr('@conjunction(@logic)', [
        '@conjunction' => $this->defaultConjunction,
        '@logic' => join(',', range(1, sizeof($this->conditions)))
      ]);
      $this->setLogic($logic);
    }

    $parser = new ParenthesisParser();
    $structure = $parser->parse($this->logic);

    if (empty($base)) {
      $this->setConditionsOnBase($query, $structure, $query);
    } else {
      $this->setConditionsOnBase($base, $structure, $query);
    }

    return $query;
  }

  /**
   * This function will be called recursively to set the conditions in a nested way on the provided base (which can be both a base query or a conditiongroup)
   *
   * @param $base \Drupal\Core\Entity\Query\QueryInterface|\Drupal\Core\Entity\Query\ConditionInterface
   * @param array $logic
   * @param QueryInterface $drupalQuery
   * @return void
   */
  private function setConditionsOnBase($base, array $logic, QueryInterface $drupalQuery): void
  {
    $conditionGroup = null;
    foreach ($logic as $key => $value) {
      if (is_array($value)) {
        if (empty($conditionGroup)) {
          throw new InvalidQueryException();
        } else {
          $this->setConditionsOnBase($conditionGroup, $value, $drupalQuery);
        }
      } else if (strtoupper($value) === 'OR') {
        if (!empty($conditionGroup)) {
          $base->condition($conditionGroup);
        }

        $conditionGroup = $drupalQuery->orConditionGroup();
      } else if (strtoupper($value) === 'AND') {
        if (!empty($conditionGroup)) {
          $base->condition($conditionGroup);
        }

        $conditionGroup = $drupalQuery->andConditionGroup();
      } else if (is_numeric($value)) {
        // check for condition in list
        if (array_key_exists($value - 1, $this->conditions)) {
          $condition = $this->conditions[$value - 1];
          if ($condition instanceof Condition) {
            $condition->addQueryCondition($base, $drupalQuery);
          } else if ($condition instanceof ConditionGroup) {
            $condition->applyConditionsOnQuery($drupalQuery, $base);
          }
        } else {
          // Condition doesnt exist, ignore it
        }
      }
    }

    if (!empty($conditionGroup)) {
      $base->condition($conditionGroup);
    }
  }
}
