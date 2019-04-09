<?php

namespace Drupal\spectrum\Query;

use Drupal\gds\Data\ChunkedIterator;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\Model;
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
  public function fetchCollection() : PolymorphicCollection
  {
    $collection = PolymorphicCollection::forgeNew(null);
    $entities = $this->fetch();

    foreach($entities as $entity)
    {
      $modelClass = Model::getModelClassForEntity($entity);
      $model = $modelClass::forgeByEntity($entity);

      $collection->put($model);
      $collection->putOriginal($model);
    }

    return $collection;
  }
}
