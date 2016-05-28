<?php

namespace Drupal\spectrum\Query;

class BundleQuery extends EntityQuery
{
  protected $bundle;

  public $conditions = array();
  public $sortOrders = array();
  public $rangeStart;
  public $rangeLength;

  public function __construct($entityType, $bundle)
  {
    parent::__construct($entityType);
    $this->bundle = $bundle;

    // first of all, lets filter by bundle, keep in mind that user is an exception, no type field for user even though there is a bundle defined
    if(!empty($this->bundle))
    {
      $this->addCondition(new Condition('type', '=', $this->bundle));
    }
  }
}
