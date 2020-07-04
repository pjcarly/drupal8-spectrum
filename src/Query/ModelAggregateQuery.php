<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\Query\QueryAggregateInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\Model;

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
   * @var bool
   *   Indicates whether to use Spectrum Access Policy.
   */
  protected $useAccessPolicy;

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
    if (!empty($this->bundle)) {
      $this->addBaseCondition(new Condition('type', '=', $modelType::bundle()));
    }
  }

  /**
   * @return bool
   */
  public function useAccessPolicy(): bool
  {
    return $this->useAccessPolicy;
  }

  /**
   * @param bool $useAccessPolicy
   *
   * @return self
   */
  public function setUseAccessPolicy(bool $useAccessPolicy): self
  {
    $this->useAccessPolicy = $useAccessPolicy;
    return $this;
  }

  /**
   * @inheritDoc
   */
  protected function getBaseQuery(): QueryAggregateInterface
  {
    $query = parent::getBaseQuery();

    if ($this->useAccessPolicy) {
      $query->addTag('spectrum_query_use_access_policy');
      $query->addMetaData('spectrum_query', $this);
    }

    return $query;
  }

  /**
   * @param \Drupal\Core\Database\Query\AlterableInterface $query
   */
  public function executeAccessPolicy(AlterableInterface $query)
  {
    /** @var \Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface $accessPolicy */
    $accessPolicy = $this->modelType::getAccessPolicy();
    $accessPolicy->onQuery($query);
  }
}
