<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Entity\Query\QueryAggregateInterface;

/**
 * This class provides base functionality for different query types
 */
class AggregateQuery extends QueryBase
{
  /**
   * Here the Aggregations are stored
   *
   * @var Aggregation[]
   */
  protected $aggregations = [];

  /**
   * All the fields where groupings were added
   *
   * @var string[]
   */
  private $groupings;

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

    if ($this->accessPolicy) {
      $query->addTag('spectrum_query_use_access_policy');
      $query->addMetaData('spectrum_query', $this);
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
