<?php

/**
 * Created by PhpStorm.
 * User: bart
 * Date: 14/12/18
 * Time: 10:35
 */

namespace Drupal\spectrum\Data;

use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Provides an Iterator class for dealing with large amounts of entities
 * but not loading them all into memory.
 */
class ChunkedIterator implements \IteratorAggregate, \Countable
{

  /**
   * The entity storage controller to load entities.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * An array of entity IDs to iterate over.
   *
   * @var array
   */
  protected $entityIds;

  /**
   * The size of each chunk of loaded entities.
   *
   * This will also be the amount of cached entities stored before clearing the
   * static cache.
   *
   * @var int
   */
  protected $chunkSize;

  /**
   * ChunkedIterator constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage_controller
   * @param array $ids
   * @param int $chunk_size
   */
  public function __construct(
    EntityStorageInterface $entity_storage_controller,
    array $ids,
    $chunk_size = 50
  ) {
    // Create a clone of the storage controller so the static cache of the
    // actual storage controller remains intact.
    $this->entityStorage = clone $entity_storage_controller;
    // Make sure we don't use a keyed array.
    $this->entityIds = array_values($ids);
    $this->chunkSize = (int) $chunk_size;
  }

  /**
   * Implements \Countable::count().
   */
  public function count()
  {
    return count($this->entityIds);
  }

  /**
   * Implements \IteratorAggregate::GetIterator()
   */
  public function getIterator()
  {
    foreach (array_chunk($this->entityIds, $this->chunkSize) as $ids_chunk) {
      foreach ($this->loadEntities($ids_chunk) as $id => $entity) {
        yield $id => $entity;
      }
    }
  }

  /**
   * Loads a set of entities.
   *
   * This depends on the cacheLimit property.
   *
   * @param array $ids
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   */
  protected function loadEntities(array $ids)
  {
    // Reset any previously loaded entities then load the current set of IDs.
    //    $this->entityStorage->resetCache();

    // https://www.drupal.org/project/drupal/issues/2577417

    /** @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $cache */
    $cache = \Drupal::service('entity.memory_cache');
    $cache->deleteAll();

    return $this->entityStorage->loadMultiple($ids);
  }
}
