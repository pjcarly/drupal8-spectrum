<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * This class provides base functionality for different query types
 */
abstract class Query
{
  /**
   * This array holds the base conditions, no matter what, they will always be applied on the query, regardless of logic or implementation
   *
   * @var array
   */
  protected $baseConditions = [];

  /**
   * This holds all the Conditions on the query, and will be applied in the order you add them.
   *
   * @var array
   */
  public $conditions = [];

  /**
   * Here the ConditionGroups are stored, the condition groups will be applied in the order you add them.
   *
   * @var array
   */
  public $conditionGroups = [];

  /**
   * Here the Query/Order are stored, the orders will be applied in the order you add them
   *
   * @var array
   */
  public $sortOrders = [];

  /**
   * The start of the range you want to return
   *
   * @var int
   */
  public $rangeStart;

  /**
   * The length of the range you want to return
   *
   * @var int
   */
  public $rangeLength;

  /**
   * THe logic that will be applied to the conditions (not baseConditions, and not ConditionGroups)
   *
   * @var string
   */
  public $conditionLogic;

  /**
   * Potential Drupal tag you want to add to the query
   *
   * @var string
   */
  public $tag;

  /**
   * Set a tag you want to add to the query
   *
   * @param string $tag
   * @return Query
   */
  public function setTag(string $tag) : Query
  {
    $this->tag = $tag;
    return $this;
  }

  /**
   * Add a Condition that will always be applied to the query, no matter what the logic is
   *
   * @param Condition $condition
   * @return Query
   */
  public function addBaseCondition(Condition $condition) : Query
  {
    $this->baseConditions[] = $condition;
    return $this;
  }

  /**
   * Add a condition to the query. If you set a conditionlogic, the numbers will be the order in which the conditions were added to the query
   *
   * @param Condition $condition
   * @return Query
   */
  public function addCondition(Condition $condition) : Query
  {
    $this->conditions[] = $condition;
    return $this;
  }

  /**
   * Add a Query/ConditionGroup, in case multiple groups are added and/or conditions, they will be combined through AND.
   *
   * @param ConditionGroup $conditionGroup
   * @return Query
   */
  public function addConditionGroup(ConditionGroup $conditionGroup) : Query
  {
    $this->conditionGroups[] = $conditionGroup;
    return $this;
  }

  /**
   * Sets the limit of amount of results you want to return, this will override any range that was previously set
   *
   * @param integer $limit
   * @return Query
   */
  public function setLimit(int $limit) : Query
  {
    $this->rangeStart = 0;
    $this->rangeLength = $limit;
    return $this;
  }

  /**
   * Checks if this query has a limit defined
   *
   * @return boolean
   */
  public function hasLimit() : bool
  {
    return !empty($this->rangeLength);
  }

  /**
   * Set the conditionlogic that needs to be appled to the conditions that were added. For example: "OR(1,2, AND(3,4, OR(1,5))"
   *
   * @param string $conditionLogic
   * @return Query
   */
  public function setConditionLogic(string $conditionLogic) : Query
  {
    $this->conditionLogic = $conditionLogic;
    return $this;
  }

  /**
   * Define a range for the results. this will override any limit that was previously set
   *
   * @param integer $start
   * @param integer $length
   * @return Query
   */
  public function setRange(int $start, int $length) : Query
  {
    $this->rangeStart = $start;
    $this->rangeLength = $length;
    return $this;
  }

  /**
   * Add a sortorder to the query, the orders will be applied in the order they were added
   *
   * @param Order $order
   * @return Query
   */
  public function addSortOrder(Order $order) : Query
  {
    $this->sortOrders[$order->fieldName] = $order;
    return $this;
  }

  /**
   * Check if a sort order exists for a certain field
   *
   * @param string $fieldName
   * @return boolean
   */
  public function hasSortOrderForField(string $fieldName) : bool
  {
    return array_key_exists($fieldName, $this->sortOrders);
  }

  /**
   * Remove all the sort orders from the query
   *
   * @return Query
   */
  public function clearSortOrders() : Query
  {
    $this->sortOrders = [];
    return $this;
  }

  /**
   * Return a DrupalQuery with all the conditions and other configurations applied to the query
   *
   * @return QueryInterface
   */
  public function getQuery() : QueryInterface
  {
    $query = $this->getBaseQuery();

    // add ranges and limits if needed
    if(!empty($this->rangeLength))
    {
      $query->range($this->rangeStart, $this->rangeLength);
    }

    return $query;
  }

  /**
   * Returns a Drupal Count Query with all the conditions and other configurations applied (except for the range or limit)
   *
   * @return QueryInterface
   */
  public function getTotalCountQuery() : QueryInterface
  {
    $query = $this->getBaseQuery();
    $query->count();
    return $query;
  }

  /**
   * Return a DrupalQuery with all the conditions and other configurations applied (except for the range or limit)
   *
   * @return QueryInterface
   */
  private function getBaseQuery() : QueryInterface
  {
    // We abstracted the getQuery and getTotalCountQuery functions in this function, to avoid duplicate code
    $query = \Drupal::entityQuery($this->entityType);

    // next we check for conditions and add them if needed

    // Base conditions must always be applied, regardless of the logic
    foreach($this->baseConditions as $condition)
    {
      $condition->addQueryCondition($query);
    }

    // We might have a logic, lets check for it
    if(empty($this->conditionLogic))
    {
      foreach($this->conditions as $condition)
      {
        $condition->addQueryCondition($query);
      }
    }
    else
    {
      // A logic was provided, we add all the conditions on the query to a ConditionGroup
      // Apply the logic, and then add pass in the drupal query to apply the conditions with logic on.
      $conditionGroup = new ConditionGroup();
      $conditionGroup->setLogic($this->conditionLogic);

      foreach($this->conditions as $condition)
      {
        $conditionGroup->addCondition($condition);
      }

      $conditionGroup->applyConditionsOnQuery($query);
    }

    // Next the possible added condition groups
    foreach($this->conditionGroups as $conditionGroup)
    {
      $conditionGroup->applyConditionsOnQuery($query);
    }

    // and finally apply an order if needed
    foreach($this->sortOrders as $sortOrder)
    {
      $query->sort($sortOrder->fieldName, $sortOrder->direction, $sortOrder->langcode);
    }

    if(!empty($this->tag))
    {
      $query->addTag($this->tag);
    }

    return $query;
  }

  /**
   * Execute the query, and return the entities in an array
   *
   * @return array
   */
  public function fetch() : array
  {
    $ids = $this->fetchIds();

    if($this->entityType === 'user')
    {
      // ugly fix for custom fields on User
      return empty($ids) ? [] : \Drupal\user\Entity\User::loadMultiple($ids);
    }
    else
    {
      $store = \Drupal::entityManager()->getStorage($this->entityType);
      return empty($ids) ? [] : $store->loadMultiple($ids);
    }
  }

  /**
   * Execute the query and return only the found ids in an array
   *
   * @return array
   */
  public function fetchIds() : array
  {
    $query = $this->getQuery();
    $result = $query->execute();

    return empty($result) ? [] : $result;
  }

  /**
   * Execute the query and return the first id of the result
   *
   * @return string|null
   */
  public function fetchId() : ?string
  {
    $ids = $this->fetchIds();

    return empty($ids) ? null : array_shift($ids);
  }

  /**
   * Execute the query, and return the first result entity
   *
   * @return EntityInterface|null
   */
  public function fetchSingle() : ?EntityInterface
  {
    $id = $this->fetchId();

    if($this->entityType === 'user')
    {
      // ugly fix for custom fields on User
      return empty($id) ? null : \Drupal\user\Entity\User::load($id);
    }
    else
    {
      $store = \Drupal::entityManager()->getStorage($this->entityType);
      return empty($id) ? null : $store->load($id);
    }
  }

  /**
   * Fetch the total query for this query, ignoring the ranges or limits
   *
   * @return integer
   */
  public function fetchTotalCount() : int
  {
    $query = $this->getTotalCountQuery();
    $result = $query->execute();

    return $result;
  }

  /**
   * Get this holds all the Conditions on the query, and will be applied in the order you add them.
   *
   * @return  array
   */
  public function getConditions() : array
  {
    return $this->conditions;
  }

  /**
   * Get this array holds the base conditions, no matter what, they will always be applied on the query, regardless of logic or implementation
   *
   * @return  array
   */
  public function getBaseConditions() : array
  {
    return $this->baseConditions;
  }

  /**
   * Get here the ConditionGroups are stored, the condition groups will be applied in the order you add them.
   *
   * @return  array
   */
  public function getConditionGroups() : array
  {
    return $this->conditionGroups;
  }

  /**
   * This function will copy all the base conditions, conditions and condition groups from the provided query, into this query
   *
   * @param Query $query
   * @return Query
   */
  public function copyConditionsFrom(Query $query) : Query
  {
    foreach($query->getBaseConditions() as $baseCondition)
    {
      $this->addBaseCondition($baseCondition);
    }

    foreach($query->getConditions() as $condition)
    {
      $this->addCondition($condition);
    }

    foreach($query->getConditionGroups() as $conditionGroup)
    {
      $this->addConditionGroup($conditionGroup);
    }

    return $this;
  }
}
