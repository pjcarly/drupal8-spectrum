<?php

namespace Drupal\spectrum\Query;

class BundleQuery extends EntityQuery
{
  /**
   * The bundle you are querying
   *
   * @var string
   */
  protected $bundle;

  /**
   * @param string $entityType The entity type you want to query
   * @param string|null $bundle The bundle you want to query, null in case the entity doesnt have a Bundle
   */
  public function __construct(string $entityType, ?string $bundle)
  {
    parent::__construct($entityType);
    $this->bundle = $bundle;

    // first of all, lets filter by bundle, keep in mind that user is an exception, no type field for user even though there is a bundle defined
    if(!empty($this->bundle))
    {
      $this->addBaseCondition(new Condition('type', '=', $this->bundle));
    }
  }
}
