<?php

namespace Drupal\spectrum\Model;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

interface ModelServiceInterface
{
  /**
   * Returns a array of Fully Qualified Class Names, with registered Model Classes in the system
   * This service should be implemented once by every Drupal installation using Spectrum.
   *
   * @return string[]
   */
  public function getRegisteredModelClasses(): array;

  /**
   * Clears the drupal entity cache for the provided model
   *
   * @param string $modelClass
   * @return self
   */
  public function clearDrupalEntityCacheForModelClass(string $modelClass): self;

  /**
   * Loops over every registered model, and clears the drupal entity cache in memory
   *
   * @return self
   */
  public function clearDrupalEntityCachesForAllModels(): self;

  /**
   * Undocumented function
   *
   * @param string $modelClass
   * @return string
   */
  public function getModelClassKey(string $modelClass): string;

  /**
   * Get a unique key for this model class
   *
   * @param string $entity
   * @param string|null $bundle
   * @return string
  
   */
  public function getKeyForEntityAndBundle(string $entity, ?string $bundle): string;

  /**
   * Checks if there is a Model Class defined for the Entity / Bundle
   *
   * @param string $entity
   * @param string|null $bundle
   * @return boolean
   */
  public function hasModelClassForEntityAndBundle(string $entity, ?string $bundle): bool;

  /**
   * Returns the fully qualified classname for the provided entity/bundle
   *
   * @param string $entity
   * @param string|null $bundle
   * @return string
   */
  public function getModelClassForEntityAndBundle(string $entity, ?string $bundle): string;

  /**
   * Returns the corresponding modelclass for an entity instance.
   *
   * @param EntityInterface $entityInstance
   * @return string
   */
  public function getModelClassForEntity(EntityInterface $entityInstance): string;

  /**
   * Returns the drupal field definitions for the entity of this Model
   *
   * @return FieldDefinitionInterface[]
   */
  public function getFieldDefinitions(string $modelClass): array;

  /**
   * Returns the drupal field definitions for the entity of this Model
   *
   * @return FieldDefinitionInterface|null
   */
  public function getFieldDefinition(string $modelClass, string $fieldName): ?FieldDefinitionInterface;

  /**
   * Returns a Drupal Entity Type for the provided modelclass
   *
   * @param string $modelClass
   * @return EntityTypeInterface
   */
  public function getEntityType(string $modelClass): EntityTypeInterface;
}
