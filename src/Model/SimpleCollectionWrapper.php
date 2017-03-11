<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Collection;

use Drupal\spectrum\Model\SimpleModelWrapper;

class SimpleCollectionWrapper implements \IteratorAggregate
{
  // This class exposes magic getters to get values from a model without having to know the drupal implementation
  // Useful for within Email templates for example, where we can just get {{ account.name }} instead of {{ account.entity.title.value }}
  private $collection;

  public function __construct(Collection $collection)
  {
    $this->collection = $collection;
  }

  public function getIterator()
  {
    // This function makes it possible to loop over a collection, we are just passing the $models as the loopable array
    $simpleModels = [];

    foreach($this->collection as $key => $model)
    {
      $simpleModels[$key] = new SimpleModelWrapper($model);
    }

    return new \ArrayIterator($simpleModels);
  }


  public function __get($property)
  {
    if (property_exists($this->collection, $property))
    {
      return $this->collection->$property;
    }
    else // lets check for pseudo properties
    {
      switch($property)
      {
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
    }
  }

  public function __isset($property)
  {
    // Needed for twig to be able to access relationship via magic getter
    return property_exists($this->collection, $property) || in_array($property, array('size', 'isEmpty', 'entities'));
  }
}
