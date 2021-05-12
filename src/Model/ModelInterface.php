<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Query\Query;

/**
 * Interface ModelInterface
 *
 * @package Drupal\spectrum\Model
 */
interface ModelInterface
{

  /**
   * The entity type of this model (for example "node"), this should be defined
   * in every subclass
   *
   * @var string
   * @return string
   */
  public static function entityType(): string;

  /**
   * The bundle of this model (for example "article"), this should be defined
   * in every subclass
   *
   * @var string
   * @return string
   */
  public static function bundle(): string;

  /**
   * What access policy should be used to access records of this model
   * 
   * @return AccessPolicyInterface
   */
  public static function getAccessPolicy(): AccessPolicyInterface;

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
