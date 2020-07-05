<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Database\Query\Condition as DrupalCondition;
use Drupal\Core\Entity\Query\QueryAggregateInterface;

/**
 * This class provides base functionality for different query types
 */
class AggregateQuery
{
  /**
   * This array holds the base conditions, no matter what, they will always be applied on the query, regardless of logic or implementation
   *
   * @var Condition[]
   */
  protected $baseConditions = [];

  /**
   * This holds all the Conditions on the query, and will be applied in the order you add them.
   *
   * @var Condition[]
   */
  protected $conditions = [];

  /**
   * Here the ConditionGroups are stored, the condition groups will be applied in the order you add them.
   *
   * @var ConditionGroup[]
   */
  protected $conditionGroups = [];

  /**
   * Here the Query/Order are stored, the orders will be applied in the order you add them
   *
   * @var Order[]
   */
  protected $sortOrders = [];

  /**
   * Here the Aggregations are stored
   *
   * @var Aggregation[]
   */
  protected $aggregations = [];

  /**
   * The logic that will be applied to the conditions (not baseConditions, and not ConditionGroups)
   *
   * @var string
   */
  protected $conditionLogic;

  /**
   * All the fields where groupings were added
   *
   * @var string[]
   */
  private $groupings;

  /**
   * Potential Drupal tag you want to add to the query
   *
   * @var string
   */
  protected $tag;

  /**
   * The entity type you want to query
   *
   * @var string
   */
  protected $entityType;

  /**
   * @param string $entityType The entity type you want to query
   */
  public function __construct(string $entityType)
  {
    $this->entityType = $entityType;
  }

  /**
   * Set a tag you want to add to the query
   *
   * @param string $tag
   * @return self
   */
  public function setTag(string $tag): self
  {
    $this->tag = $tag;
    return $this;
  }

  /**
   * Add a Condition that will always be applied to the query, no matter what the logic is
   *
   * @param Condition $condition
   * @return self
   */
  public function addBaseCondition(Condition $condition): self
  {
    $this->baseConditions[] = $condition;
    return $this;
  }

  /**
   * Add a condition to the query. If you set a conditionlogic, the numbers will be the order in which the conditions were added to the query
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
   * Add a Query/ConditionGroup, in case multiple groups are added and/or conditions, they will be combined through AND.
   *
   * @param ConditionGroup $conditionGroup
   * @return self
   */
  public function addConditionGroup(ConditionGroup $conditionGroup): self
  {
    $this->conditionGroups[] = $conditionGroup;
    return $this;
  }

  /**
   * Set the conditionlogic that needs to be appled to the conditions that were added. For example: "OR(1,2, AND(3,4, OR(1,5))"
   *
   * @param string $conditionLogic
   * @return self
   */
  public function setConditionLogic(string $conditionLogic): self
  {
    $this->conditionLogic = $conditionLogic;
    return $this;
  }

  /**
   * @param Aggregation $aggregation
   * @return self
   */
  public function addAggregation(Aggregation $aggregation): self
  {
    $this->aggregations[] = $aggregation;
    return $this;
  }

  /**
   * @return array
   */
  public function getAggregations(): array
  {
    return $this->aggregations;
  }

  /**
   * Define a range for the results. this will override any limit that was previously set
   *
   * @param integer $start
   * @param integer $length
   * @return self
   */
  public function setRange(int $start, int $length): self
  {
    $this->rangeStart = $start;
    $this->rangeLength = $length;
    return $this;
  }

  /**
   * Add a sortorder to the query, the orders will be applied in the order they were added
   *
   * @param Order $order
   * @return self
   */
  public function addSortOrder(Order $order): self
  {
    $this->sortOrders[$order->getFieldName()] = $order;
    return $this;
  }

  /**
   * Check if a sort order exists for a certain field
   *
   * @param string $fieldName
   * @return boolean
   */
  public function hasSortOrderForField(string $fieldName): bool
  {
    return array_key_exists($fieldName, $this->sortOrders);
  }

  /**
   * Remove all the sort orders from the query
   *
   * @return self
   */
  public function clearSortOrders(): self
  {
    $this->sortOrders = [];
    return $this;
  }

  /**
   * Return a DrupalQuery with all the conditions and other configurations applied to the query
   *
   * @return QueryAggregateInterface
   */
  public function getQuery(): QueryAggregateInterface
  {
    $query = $this->getBaseQuery();

    return $query;
  }

  /**
   * Return a DrupalQuery with all the conditions and other configurations applied (except for the range or limit)
   self
   * @return AggregateQueryInterface
   */
  protected function getBaseQuery(): QueryAggregateInterface
  {
    // We abstracted the getQuery and getTotalCountQuery functions in this function, to avoid duplicate code
    $query = \Drupal::entityQueryAggregate($this->entityType);

    // next we check for conditions and add them if needed

    // Base conditions must always be applied, regardless of the logic
    foreach ($this->baseConditions as $condition) {
      $condition->addQueryCondition($query, $query);
    }

    // We might have a logic, lets check for it
    if (empty($this->conditionLogic)) {
      foreach ($this->conditions as $condition) {
        $condition->addQueryCondition($query, $query);
      }
    } else {
      // A logic was provided, we add all the conditions on the query to a ConditionGroup
      // Apply the logic, and then add pass in the drupal query to apply the conditions with logic on.
      $conditionGroup = new ConditionGroup();
      $conditionGroup->setLogic($this->conditionLogic);

      foreach ($this->conditions as $condition) {
        $conditionGroup->addCondition($condition);
      }

      $conditionGroup->applyConditionsOnQuery($query);
    }

    // Next the possible added condition groups
    foreach ($this->conditionGroups as $conditionGroup) {
      $conditionGroup->applyConditionsOnQuery($query);
    }

    // Next the aggregations
    /** @var Aggregation $aggregation */
    foreach ($this->getAggregations() as $aggregation) {
      $query->aggregate($aggregation->getFieldName(), $aggregation->getAggregateFunction());
    }

    // Next the groupings
    foreach ($this->getGroupings() as $grouping) {
      $query->groupBy($grouping);
    }

    // and finally apply an order if needed
    foreach ($this->sortOrders as $sortOrder) {
      $query->sort($sortOrder->getFieldName(), $sortOrder->getDirection(), $sortOrder->getLangcode());
    }

    if (!empty($this->tag)) {
      $query->addTag($this->tag);
    }

    return $query;
  }

  /**
   * Execute the query and return the results
   *
   * @return array
   */
  public function fetchResults(): array
  {
    $query = $this->getQuery();
    $result = $query->execute();

    return empty($result) ? [] : $result;
  }

  /**
   * Get this holds all the Conditions on the query, and will be applied in the order you add them.
   *
   * @return  Condition[]
   */
  public function getConditions(): array
  {
    return $this->conditions;
  }

  /**
   * Get this array holds the base conditions, no matter what, they will always be applied on the query, regardless of logic or implementation
   *
   * @return  Condition[]
   */
  public function getBaseConditions(): array
  {
    return $this->baseConditions;
  }

  /**
   * Get here the ConditionGroups are stored, the condition groups will be applied in the order you add them.
   *
   * @return  ConditionGroup[]
   */
  public function getConditionGroups(): array
  {
    return $this->conditionGroups;
  }

  /**
   * This function will copy all the base conditions, conditions and condition groups from the provided query, into this query
   *
   * @param Query $query
   * @return self
   */
  public function copyConditionsFrom(Query $query): self
  {
    foreach ($query->getBaseConditions() as $baseCondition) {
      $this->addBaseCondition($baseCondition);
    }

    foreach ($query->getConditions() as $condition) {
      $this->addCondition($condition);
    }

    foreach ($query->getConditionGroups() as $conditionGroup) {
      $this->addConditionGroup($conditionGroup);
    }

    $conditionLogic = $query->getConditionLogic();
    if (!empty($conditionLogic)) {
      $this->setConditionLogic($conditionLogic);
    }

    return $this;
  }

  public function copySortOrdersFrom(Query $query): self
  {
    foreach ($query->getSortOrders() as $sortOrder) {
      $this->addSortOrder($sortOrder);
    }

    return $this;
  }

  /**
   * Returns all the sort orders in an array
   *
   * @return Order[]
   */
  public function getSortOrders(): array
  {
    return $this->sortOrders;
  }

  /**
   * Get potential Drupal tag you want to add to the query
   *
   * @return  string|null
   */
  public function getTag(): ?string
  {
    return $this->tag;
  }

  /**
   * Get the logic that will be applied to the conditions (not baseConditions, and not ConditionGroups)
   *
   * @return  string|null
   */
  public function getConditionLogic(): ?string
  {
    return $this->conditionLogic;
  }

  /**
   * Get the entity type you want to query
   *
   * @return  string
   */
  public function getEntityType(): string
  {
    return $this->entityType;
  }

  /**
   * @return string[]
   */
  public function getGroupings(): array
  {
    return $this->groupings ?? [];
  }

  /**
   * @param string $fieldName
   * @return self
   */
  public function addGrouping(string $fieldName): self
  {
    $this->groupings[] = $fieldName;
    return $this;
  }
}
