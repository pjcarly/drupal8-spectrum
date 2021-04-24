<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;

/**
 * The ModelQuery is an extension of a regular query, with extra methods to
 * either directly return a Model or a Collection.
 */
class ModelAggregateQuery extends AggregateQuery
{
  /**
   * The fully qualified classname of the modeltype you are querying.
   *
   * @var string
   */
  public $modelType;

  /**
   * ModelQuery constructor.
   *
   * @param string $modelType
   *   The fully qualified classname of the modeltype you
   *   are querying, the entity type and bundle will be pulled from the model
   *   class.
   */
  public function __construct(string $modelType)
  {
    parent::__construct($modelType::entityType());
    $this->modelType = $modelType;
    if (!empty($modelType::bundle())) {
      $this->addBaseCondition(new Condition('type', '=', $modelType::bundle()));
    }
  }

  /**
   * Use the accesspolicy of the modelclass to use in the query
   *
   * @return self
   */
  public function useModelAccessPolicy(): self
  {
    /** @var AccessPolicyInterface $accessPolicy */
    $accessPolicy = $this->modelType::getAccessPolicy();
    $this->setAccessPolicy($accessPolicy);
    return $this;
  }
}
