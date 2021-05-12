<?php

namespace Drupal\spectrum\Model;

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
  public function clearDrupalEntityCacheForModel(string $modelClass): self;

  /**
   * Loops over every registered model, and clears the drupal entity cache in memory
   *
   * @return self
   */
  public function clearDrupalEntityCachesForAllModels(): self;
}
