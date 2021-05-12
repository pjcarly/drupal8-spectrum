<?php

namespace Drupal\spectrum\Model;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

abstract class ModelService implements ModelServiceInterface
{
  protected LoggerInterface $logger;
  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(LoggerInterface $logger, EntityTypeManagerInterface $entityTypeManager)
  {
    $this->logger = $logger;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function clearDrupalEntityCacheForModel(string $modelClass): self
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
      $this->clearDrupalEntityCacheForModel($modelClass);
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public abstract function getRegisteredModelClasses(): array;
}
