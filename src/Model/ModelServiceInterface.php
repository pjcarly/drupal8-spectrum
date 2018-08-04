<?php

namespace Drupal\spectrum\Model;

interface ModelServiceInterface
{
  /**
   * Returns a array of Fully Qualified Class Names, with registered Model Classes in the system
   *
   * @return array
   */
  public function getRegisteredModelClasses() : array;
}
