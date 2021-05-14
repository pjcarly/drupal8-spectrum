<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\ModelServiceInterface;
use Drupal\spectrum\Model\PolymorphicCollection;

/**
 * The MultiModelQuery is an extension of a regular Entity Query, with extra methods to return a Polymorphic Collection
 */
class MultiModelQuery extends EntityQuery
{
  /**
   * Execute the query, and return a polymorphic collection for all the found entities
   *
   * @return Collection
   */
  public function fetchCollection(): PolymorphicCollection
  {
    $collection = PolymorphicCollection::forgeNew(null);
    $entities = $this->fetch();
    /** @var ModelServiceInterface $modelService */
    $modelService = \Drupal::service("spectrum.model");

    foreach ($entities as $entity) {
      $modelClass = $modelService->getModelClassForEntity($entity);
      $model = $modelClass::forgeByEntity($entity);

      $collection->put($model);
      $collection->putOriginal($model);
    }

    return $collection;
  }
}
