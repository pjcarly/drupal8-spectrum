<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Collection;

use Drupal\spectrum\Model\SimpleModelWrapper;

/**
 * This class is a wrapper of collection, with the simple use of using collections in twig templates without having to know the Drupal implementation
 * This class exposes magic getters to get values from a model without having to know the drupal implementation
 * Useful for within Email templates for example, where we can just get {{ account.name }} instead of {{ account.entity.title.value }}
 */
class SimpleCollectionWrapper implements \IteratorAggregate
{
  /**
   * The wrapped Collection
   *
   * @var Collection
   */
  private $collection;

  /**
   * @param Collection $collection The collection you want to wrap
   */
  public function __construct(Collection $collection)
  {
    $this->collection = $collection;
  }

  /**
   * Return the wrapped collection
   *
   * @return Collection
   */
  public function getCollection(): Collection
  {
    return $this->collection;
  }

  /**
   * Implementation of \IteratorAggregate, This function makes it possible to loop over a collection, we are just passing the $models as the loopable array
   *
   * @return void
   */
  public function getIterator()
  {
    $simpleModels = [];

    foreach ($this->collection as $key => $model) {
      $simpleModels[$key] = new SimpleModelWrapper($model);
    }

    return new \ArrayIterator($simpleModels);
  }

  /**
   * Magic getter to expose Collection logic
   *
   * @param string $property
   * @return void
   */
  public function __get($property)
  {
    if (property_exists($this->collection, $property)) {
      return $this->collection->$property;
    } else if (in_array($property, ['size', 'isEmpty', 'entities'])) // lets check for pseudo properties
    {
      switch ($property) {
        case "size":
          return $this->collection->size();
          break;
        case "isEmpty":
          return $this->collection->isEmpty();
          break;
        case "entities":
          return $this->collection->getEntities();
          break;
      }
    } else if ($this->collection->hasRelationship($property)) {
      $collection = $this->collection->get($property);
      return new SimpleCollectionWrapper($collection);
    }
  }

  /**
   * Magic issetter for twig templates
   *
   * @param string $property
   * @return boolean
   */
  public function __isset($property)
  {
    // Needed for twig to be able to access relationship via magic getter
    return property_exists($this->collection, $property)
      || in_array($property, ['size', 'isEmpty', 'entities'])
      || $this->collection->hasRelationship($property);
  }
}
