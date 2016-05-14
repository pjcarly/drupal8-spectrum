<?php

namespace Drupal\spectrum\Query;

abstract class Query
{
  public $conditions = array();
  public $sortOrders = array();
  public $rangeStart;
  public $rangeLength;

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

  public function hasLimit()
  {
    return !empty($this->rangeLength);
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
    $query = $this->getBaseQuery();

    // add ranges and limits if needed
    if(!empty($this->rangeLength))
    {
      $query->range($this->rangeStart, $this->rangeLength);
    }

    return $query;
  }

  public function getTotalCountQuery()
  {
    $query = $this->getBaseQuery();
    $query->count();
    return $query;
  }

  private function getBaseQuery()
  {
    // We abstracted the getQuery and getTotalCountQuery functions in this function, to avoid duplicate code
    $query = \Drupal::entityQuery($this->entityType);

    // next we check for conditions and add them if needed
    foreach($this->conditions as $condition)
    {
      $condition->addQueryCondition($query);
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

  public function fetchTotalCount()
  {
    $query = $this->getTotalCountQuery();
    $result = $query->execute();

    return $result;
  }
}
