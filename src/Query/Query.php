<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Utils\ParenthesisParser;
use Drupal\spectrum\Exceptions\InvalidQueryException;

abstract class Query
{
  protected $baseConditions = [];
  public $conditions = [];
  public $sortOrders = [];
  public $rangeStart;
  public $rangeLength;
  public $conditionLogic;
  public $tag;

  public function setTag($tag)
  {
    $this->tag = $tag;
  }

  public function addBaseCondition(Condition $condition)
  {
    $this->baseConditions[] = $condition;
  }

  public function addCondition(Condition $condition)
  {
    $this->conditions[] = $condition;
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

  public function setConditionLogic($conditionLogic)
  {
    $this->conditionLogic = $conditionLogic;
  }

  public function setRange($start, $length)
  {
    $this->rangeStart = $start;
    $this->rangeLength = $length;
  }

  public function addSortOrder(Order $order)
  {
    $this->sortOrders[$order->fieldName] = $order;
  }

  public function hasSortOrderForField($fieldName)
  {
    return array_key_exists($fieldName, $this->sortOrders);
  }

  public function clearSortOrders()
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

    // Base conditions must always be applied, regardless of the logic
    foreach($this->baseConditions as $condition)
    {
      $condition->addQueryCondition($query);
    }

    // We might have a logic, lets check for it
    if(empty($this->conditionLogic))
    {
      foreach($this->conditions as $condition)
      {
        $condition->addQueryCondition($query);
      }
    }
    else
    {
      $parser = new ParenthesisParser();
      $structure = $parser->parse($this->conditionLogic);
      $this->setConditionsOnBase($query, $structure, $query);
    }

    // and finally apply an order if needed
    foreach($this->sortOrders as $sortOrder)
    {
      $query->sort($sortOrder->fieldName, $sortOrder->direction, $sortOrder->langcode);
    }

    if(!empty($this->tag))
    {
      $query->addTag($this->tag);
    }

    return $query;
  }

  private function setConditionsOnBase($base, $logic, $query)
  {
    $conditionGroup;
    foreach($logic as $key => $value)
    {
      if(is_array($value))
      {
        if(empty($conditionGroup))
        {
          // we dont need to do anything recursive, we"ve reached the end, lets apply the conditions to the base
          throw new InvalidQueryException();
        }
        else
        {
          $this->setConditionsOnBase($conditionGroup, $value, $query);
        }
      }
      else if(strtoupper($value) === 'OR')
      {
        if(!empty($conditionGroup))
        {
          $base->condition($conditionGroup);
        }

        $conditionGroup = $query->orConditionGroup();
      }
      else if(strtoupper($value) === 'AND')
      {
        if(!empty($conditionGroup))
        {
          $base->condition($conditionGroup);
        }

        $conditionGroup = $query->andConditionGroup();
      }
      else if(is_numeric($value))
      {
        // check for condition in list
        if(array_key_exists($value-1, $this->conditions))
        {
          $condition = $this->conditions[$value-1];
          $condition->addQueryCondition($base);
        }
        else
        {
          // Condition doesnt exist, ignore it
        }
      }
    }

    if(!empty($conditionGroup))
    {
      $base->condition($conditionGroup);
    }
  }

  public function fetch()
  {
    $ids = $this->fetchIds();

    if($this->entityType === 'user')
    {
      // ugly fix for custom fields on User
      return empty($ids) ? [] : \Drupal\user\Entity\User::loadMultiple($ids);
    }
    else
    {
      $store = \Drupal::entityManager()->getStorage($this->entityType);
      return empty($ids) ? [] : $store->loadMultiple($ids);
    }
  }

  public function fetchIds()
  {
    $query = $this->getQuery();
    $result = $query->execute();

    return empty($result) ? [] : $result;
  }

  public function fetchId()
  {
    $ids = $this->fetchIds();

    return empty($ids) ? null : array_shift($ids);
  }

  public function fetchSingle()
  {
    $id = $this->fetchId();

    if($this->entityType === 'user')
    {
      // ugly fix for custom fields on User
      return empty($id) ? null : \Drupal\user\Entity\User::load($id);
    }
    else
    {
      $store = \Drupal::entityManager()->getStorage($this->entityType);
      return empty($id) ? null : $store->load($id);
    }
  }

  public function fetchTotalCount()
  {
    $query = $this->getTotalCountQuery();
    $result = $query->execute();

    return $result;
  }
}
