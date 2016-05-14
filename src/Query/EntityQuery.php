<?php

namespace Drupal\spectrum\Query;

class EntityQuery extends Query
{
  protected $entityType;

  public function __construct($entityType)
  {
    $this->entityType = $entityType;
  }
}
