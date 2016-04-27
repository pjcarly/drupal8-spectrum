<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Utils\ParenthesisParser;

class Query
{
  private $bundle;
  private $entityType;

  public $conditions = array();
  public $orders = array();
  public $limit;
  public $conditionLogic;

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
    $this->orders[] = $order;
  }

  public function addConditionLogic()
  {
    $parser = new ParenthesisParser();
    $result = $parser->parse($this->conditionLogic);
  }

  public function setLimit($limit)
  {
    $this->limit = $limit;
  }

  public function getQuery()
  {
    $query = \Drupal::entityQuery($this->entityType);

    if(!empty($this->bundle))
    {
      $this->addCondition(new Condition('type', '=', $this->bundle));
    }

    if(empty($this->conditionLogic))
    {
      foreach($this->conditions as $condition)
      {
        $condition->addQueryCondition($query);
      }
    }

    if(!empty($this->limit))
    {
      $query->range(0, $this->limit);
    }

    // foreach($this->orders as $order)
    // {
    //     $order->addQueryOrder($query);
    // }

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
