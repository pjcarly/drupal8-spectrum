<?php

namespace Drupal\spectrum\Query;

class EntityQuery extends Query
{
  /**
   * The entity type you want to query
   *
   * @var string
   */
  protected $entityType;

  /**
   * @param string $entityType The entity type you want to query
   */
  public function __construct(string $entityType)
  {
    $this->entityType = $entityType;
  }
}
