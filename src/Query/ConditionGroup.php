<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Utils\ParenthesisParser;
use Drupal\spectrum\Exceptions\InvalidOperatorException;
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
   * @var array
   */
  private $conditions;

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
   * @return ConditionGroup
   */
  public function addCondition(Condition $condition): ConditionGroup
  {
    $this->conditions[] = $condition;
    return $this;
  }

  /**
   * Returns an array containing the conditions
   *
   * @return array
   */
  public function getConditions(): array
  {
    return $this->conditions;
  }

  /**
   * Sets the conditionlogic of this conditiongroup. The numbers in the logic
   * should correspond to the order in which you added conditions to the
   * conditiongroup.
   * You can reuse the same index multiple times, then the condition will be applied multiple times
   * For example 'AND(1,2,OR(1,3,4))'
   *
   * @param string $logic
   * @return ConditionGroup
   */
  public function setLogic(string $logic): ConditionGroup
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
  public function applyConditionsOnQuery(QueryInterface $query): QueryInterface
  {
    if (empty($this->logic)) {
      throw new InvalidQueryException('No Condition logic passed for Condition Group');
    }

    $parser = new ParenthesisParser();
    $structure = $parser->parse($this->logic);

    $this->setConditionsOnBase($query, $structure, $query);
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
          $condition->addQueryCondition($base, $drupalQuery);
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
