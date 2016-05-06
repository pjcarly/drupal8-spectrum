<?php

namespace Drupal\spectrum\Query;

class Query
{
  private $bundle;
  private $entityType;

  public $conditions = array();
  public $sortOrders = array();
  public $rangeStart;
  public $rangeLength;

  public function __construct($entityType, $bundle)
  {
    $this->bundle = $bundle;
    $this->entityType = $entityType;
  }

  public function addCondition(Condition $condition)
  {
    $this->conditions[] = $condition;
  }

  public function addOrder(Order $order)
  {
    $this->sortOrders[] = $order;
  }

  public function setLimit($limit)
  {
    $this->rangeStart = 0;
    $this->rangeLength = $limit;
  }

  public function setRange($start, $length)
  {
    $this->rangeStart = $start;
    $this->rangeLength = $length;
  }

  public function addSortOrder($field, $direction = 'ASC', $langcode = null)
  {
    $this->sortOrders[] = new Order($field, $direction, $langcode);
  }

  public function clearOrders()
  {
    $this->sortOrders = array();
  }

  public function getQuery()
  {
    $query = \Drupal::entityQuery($this->entityType);

    // first of all, lets filter by bundle, keep in mind that user is an exception, no type field for user even though there is a bundle defined
    if(!empty($this->bundle) && $this->bundle !== 'user')
    {
      $this->addCondition(new Condition('type', '=', $this->bundle));
    }

    // next we check for conditions and add them if needed
    if(empty($this->conditionLogic))
    {
      foreach($this->conditions as $condition)
      {
        $condition->addQueryCondition($query);
      }
    }

    // check for range or limit
    if(!empty($this->rangeLength))
    {
      $query->range($this->rangeStart, $this->rangeLength);
    }

    // and finally apply an order if needed
    foreach($this->sortOrders as $sortOrder)
    {
      $query->sort($sortOrder->field, $sortOrder->direction, $sortOrder->langcode);
    }

    return $query;
  }

  public function fetch()
  {
    $query = $this->getQuery();
    $result = $query->execute();

    $store = \Drupal::entityManager()->getStorage($this->entityType);

    return empty($result) ? array() : $store->loadMultiple($result);
  }

  public function fetchSingle()
  {
    $query = $this->getQuery();
    $result = $query->execute();

    if(empty($result))
    {
      return null;
    }
    else
    {
      $store = \Drupal::entityManager()->getStorage($this->entityType);
      $id = array_shift($result);
      return empty($id) ? array() : $store->load($id);
    }
  }
}
