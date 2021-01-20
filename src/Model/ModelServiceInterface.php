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
}
