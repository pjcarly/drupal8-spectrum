<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Database\Query\Select;
use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * This class provides base functionality for different query types
 */
class AggregateQuery extends QueryBase
{
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * Here the Aggregations are stored
   *
   * @var Aggregation[]
   */
  protected $aggregations = [];

  private $expressionInGroupings = false;

  /**
   * All the fields where groupings were added
   *
   * @var string[]
   */
  private $groupings;

  public function __construct(string $entityType)
  {
    parent::__construct($entityType);
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
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
   * {@inheritdoc}
   */
  protected final function getDrupalQuery(): QueryInterface
  {
    return $this->entityTypeManager->getStorage($this->entityType)->getAggregateQuery();
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseQuery(): QueryAggregateInterface
  {
    /** @var QueryAggregateInterface $query */
    $query = parent::getBaseQuery();

    // Next the aggregations
    /** @var Aggregation $aggregation */
    foreach ($this->getAggregations() as $aggregation) {
      if (!$this->hasExpression($aggregation->getFieldName())) {
        $query->aggregate($aggregation->getFieldName(), $aggregation->getAggregateFunction());
      }
    }

    $this->prepareGroupings($query);

    return $query;
  }

  /**
   * @param QueryAggregateInterface $drupalQuery
   * @return self
   */
  private function prepareGroupings(QueryAggregateInterface $drupalQuery): self
  {
    // Next the groupings
    foreach ($this->getGroupings() as $grouping) {
      if ($this->hasExpression($grouping)) {
        $this->expressionInGroupings = true;
        break;
      }
    }

    if (!$this->expressionInGroupings) {
      foreach ($this->getGroupings() as $grouping) {
        $drupalQuery->groupBy($grouping);
      }
    }

    return $this;
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

  /**
   * {@inheritdoc}
   */
  public function parseExpressions(Select $drupalQuery): self
  {
    parent::parseExpressions($drupalQuery);

    if ($this->expressionInGroupings) {
      foreach ($this->groupings as $grouping) {
        $drupalQuery->groupBy($grouping);
      }
    }

    return $this;
  }
}
