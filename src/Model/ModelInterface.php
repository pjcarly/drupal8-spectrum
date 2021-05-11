<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Query;

/**
 * Interface ModelInterface
 *
 * @package Drupal\spectrum\Model
 */
interface ModelInterface
{

  /**
   * @param string|NULL $relationshipName
   *
   * @return self
   */
  public function save(string $relationshipName = NULL): self;

  /**
   * @param string $relationshipName
   * @param \Drupal\spectrum\Query\Query|null $queryToCopyFrom
   *
   * @return mixed
   */
  public function fetch(string $relationshipName, ?Query $queryToCopyFrom = null);

  /**
   * @param string $relationshipName
   * @return Collection|Model|null
   */
  public function get(string $relationshipName);


  /**
   * Checks if a model is related via a fieldrelationship currently in memory
   * @param string $relationshipName
   * @return boolean
   */
  public function isRelatedViaFieldRelationshipInMemory(string $relationshipName): bool;

  /**
   * Checks if a model is related via a referenced relationship currently in memory
   * @param string $relationshipName
   * @return boolean
   */
  public function isRelatedViaReferencedRelationshipInMemory(string $relationshipName): bool;

  /**
   * Returns all the current referenced relationships in memory
   * @return array
   */
  public function getReferencedRelationshipsInMemory(): array;

  /**
   * Returns all the current field relationships in memory
   * @return array
   */
  public function getFieldRelationshipsInMemory(): array;
}
