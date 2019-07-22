<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\Model;

/**
 * The ModelQuery is an extension of a regular query, with extra methods to
 * either directly return a Model or a Collection.
 */
class ModelQuery extends BundleQuery
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
    parent::__construct($modelType::entityType(), $modelType::bundle());
    $this->modelType = $modelType;
  }

  /**
   * Execute the query, and return a collection with all the found entities
   *
   * @return Collection
   */
  public function fetchCollection(): Collection
  {
    $entities = $this->fetch();
    return Collection::forgeByEntities($this->modelType, $entities);
  }

  /**
   * @return \Generator
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function fetchGenerator(): \Generator
  {
    foreach (parent::fetchGenerator() as $entity) {
      yield $this->modelType::forgeByEntity($entity);
    }
  }

  /**
   * Execute the query, and fetch a single Model, if multiple entities are
   * found, the first one is returned. If nothing is found, NULL is returned.
   *
   * @return Model|NULL
   */
  public function fetchSingleModel(): ?Model
  {
    $entity = $this->fetchSingle();

    if ($entity != null) {
      $modelType = $this->modelType;
      return $modelType::forgeByEntity($entity);
    } else {
      return null;
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
   * @return Query
   */
  public function setUseAccessPolicy(bool $useAccessPolicy): Query
  {
    $this->useAccessPolicy = $useAccessPolicy;
    return $this;
  }

  /**
   * @inheritDoc
   */
  protected function getBaseQuery(): QueryInterface
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
