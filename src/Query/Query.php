<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\spectrum\Data\ChunkedIterator;
use Drupal\spectrum\Runnable\BatchableInterface;

/**
 * This class provides base functionality for different query types
 */
abstract class Query extends QueryBase implements BatchableInterface
{
  private EntityTypeManagerInterface $entityTypeManager;

  /**
   * This variable stores the batch size, it is used by the BatchableInterface to process batches of queries
   *
   * @var int
   */
  protected $batchSize;

  /**
   * Here we store all the ids the batch must return, we must page over them during every batch cycle
   *
   * @var array
   */
  protected $batchIds = [];

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


  public function __construct(string $entityType)
  {
    parent::__construct($entityType);
    $this->entityTypeManager = \Drupal::service('entity_type.manager');
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalBatchedRecords(): ?int
  {
    return sizeof($this->batchIds);
  }

  /**
   * {@inheritdoc}
   */
  public function getBatchGenerator(): \Generator
  {
    // Now we copy the current query, and add a condition for the IDS we need to handle
    $storage = $this->entityTypeManager->getStorage($this->entityType);
    $entities = new ChunkedIterator($storage, $this->batchIds, $this->batchSize);

    foreach ($entities as $entity) {
      yield $entity;
    }
  }

  /**
   * Sets the size of the batch, this is needed for BatchableInterface
   *
   * @param integer $batchSize
   * @return BatchableInterface
   */
  public function setBatchSize(int $batchSize): BatchableInterface
  {
    $this->batchSize = $batchSize;
    $this->batchIds = $this->fetchIds();

    return $this;
  }

  /**
   * Sets the limit of amount of results you want to return, this will override any range that was previously set
   *
   * @param integer $limit
   * @return self
   */
  public function setLimit(int $limit): self
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
  public function hasLimit(): bool
  {
    return !empty($this->rangeLength);
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
   * @return QueryInterface
   */
  public function getQuery(): QueryInterface
  {
    $query = $this->getBaseQuery();

    // add ranges and limits if needed
    if ($this->hasLimit()) {
      $query->range($this->rangeStart, $this->rangeLength);
    }

    return $query;
  }

  /**
   * Returns a Drupal Count Query with all the conditions and other configurations applied (except for the range or limit)
   *
   * @return QueryInterface
   */
  public function getTotalCountQuery(): QueryInterface
  {
    $query = $this->getBaseQuery();
    $query->count();
    return $query;
  }

  protected final function getDrupalQuery(): QueryInterface
  {
    return $this->entityTypeManager->getStorage($this->entityType)->getQuery();
  }

  /**
   * Execute the query, and return the entities in an array
   *
   * @return EntityInterface[]
   */
  public function fetch(): array
  {
    $ids = $this->fetchIds();

    $store = $this->entityTypeManager->getStorage($this->entityType);
    return empty($ids) ? [] : $store->loadMultiple($ids);
  }

  /**
   * Execute the query and return only the found ids in an array
   *
   * @return array
   */
  public function fetchIds(): array
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
  public function fetchId(): ?string
  {
    $ids = $this->fetchIds();

    return empty($ids) ? null : array_shift($ids);
  }

  /**
   * Execute the query, and return the first result entity
   *
   * @return EntityInterface|null
   */
  public function fetchSingle(): ?EntityInterface
  {
    $id = $this->fetchId();
    $store = $this->entityTypeManager->getStorage($this->entityType);
    return empty($id) ? null : $store->load($id);
  }

  /**
   * Fetch the total query for this query, ignoring the ranges or limits
   *
   * @return integer
   */
  public function fetchTotalCount(): int
  {
    $query = $this->getTotalCountQuery();
    $result = $query->execute();

    return $result;
  }

  /**
   * Get the start of the range you want to return
   *
   * @return  int
   */
  public function getRangeStart(): int
  {
    return $this->rangeStart;
  }

  /**
   * Get the length of the range you want to return
   *
   * @return  int
   */
  public function getRangeLength(): int
  {
    return $this->rangeLength;
  }
}
