<?php

namespace Drupal\spectrum\Services;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\spectrum\Exceptions\ModelClassNotDefinedException;
use Psr\Log\LoggerInterface;

use Drupal\spectrum\Models\Image;
use Drupal\spectrum\Runnable\QueuedJob;
use Drupal\spectrum\Runnable\RegisteredJob;

class ModelService implements ModelServiceInterface
{
  protected LoggerInterface $logger;
  protected EntityTypeManagerInterface $entityTypeManager;
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * This array will hold a mapping between the modelClass keys and the modelClasses
   */
  protected array $modelClassMappings = [];

  public function __construct(
    LoggerInterface $logger,
    EntityTypeManagerInterface $entityTypeManager,
    EntityFieldManagerInterface $entityFieldManager
  ) {
    $this->logger = $logger;
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;

    $this->setModelClassMappings();
  }

  /**
   * Generates a mapping between the key and modelClass
   *
   * @return self
   */
  protected function setModelClassMappings(): self
  {
    foreach ($this->getRegisteredModelClasses() as $modelClass) {
      $key = $this->getModelClassKey($modelClass);
      $this->modelClassMappings[$key] = $modelClass;
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clearDrupalEntityCacheForModelClass(string $modelClass): self
  {
    $this->entityTypeManager
      ->getStorage($modelClass::entityType())
      ->resetCache();

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clearDrupalEntityCachesForAllModels(): self
  {
    foreach ($this->getRegisteredModelClasses() as $modelClass) {
      $this->clearDrupalEntityCacheForModelClass($modelClass);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasModelClassForEntityAndBundle(string $entity, ?string $bundle): bool
  {
    $key = $this->getKeyForEntityAndBundle($entity, $bundle);
    return array_key_exists($key, $this->modelClassMappings);
  }

  /**
   * {@inheritdoc}
   */
  public function getRegisteredModelClasses(): array
  {
    $modelClasses = [];
    //$modelClasses[] = '\Drupal\spectrum\Models\File';
    $modelClasses[] = Image::class;
    $modelClasses[] = QueuedJob::class;
    $modelClasses[] = RegisteredJob::class;

    return $modelClasses;
  }


  /**
   * {@inheritdoc}
   */
  public function getModelClassKey(string $modelClass): string
  {
    /** @var ModelInterface $modelClass */
    return $this->getKeyForEntityAndBundle($modelClass::entityType(), $modelClass::bundle());
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyForEntityAndBundle(string $entity, ?string $bundle): string
  {
    return empty($bundle) ? $entity . '.' . $entity : $entity . '.' . $bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getModelClassForEntityAndBundle(string $entity, ?string $bundle): string
  {
    if ($this->hasModelClassForEntityAndBundle($entity, $bundle)) {
      $key = $this->getKeyForEntityAndBundle($entity, $bundle);
      return $this->modelClassMappings[$key];
    } else {
      throw new ModelClassNotDefinedException('No model class for entity ' . $entity . ' and bundle ' . $bundle . ' has been defined');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getModelClassForEntity(EntityInterface $entityInstance): string
  {
    $bundle = $entityInstance->bundle();
    $entity = $entityInstance->getEntityTypeId();

    return $this->getModelClassForEntityAndBundle($entity, $bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions(string $modelClass): array
  {
    /** @var ModelInterface $modelClass */
    if (empty($modelClass::bundle())) {
      return $this->entityFieldManager->getFieldDefinitions($modelClass::entityType(), $modelClass::entityType());
    } else {
      return $this->entityFieldManager->getFieldDefinitions($modelClass::entityType(), $modelClass::bundle());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition(string $modelClass, string $fieldName): ?FieldDefinitionInterface
  {
    $fieldDefinition = null;
    $fieldDefinitions = $this->getFieldDefinitions($modelClass);
    if (array_key_exists($fieldName, $fieldDefinitions)) {
      $fieldDefinition = $fieldDefinitions[$fieldName];
    }
    return $fieldDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType(string $modelClass): EntityTypeInterface
  {
    /** @var ModelInterface $modelClass */
    return $this->entityTypeManager->getDefinition($modelClass::entityType());
  }
}
