<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Entity\Query\QueryInterface;

abstract class Query
{
  protected $baseConditions = [];
  public $conditions = [];
  public $conditionGroups = [];
  public $sortOrders = [];
  public $rangeStart;
  public $rangeLength;
  public $conditionLogic;
  public $tag;

  public function setTag(string $tag) : Query
  {
    $this->tag = $tag;
    return $this;
  }

  public function addBaseCondition(Condition $condition) : Query
  {
    $this->baseConditions[] = $condition;
    return $this;
  }

  public function addCondition(Condition $condition) : Query
  {
    $this->conditions[] = $condition;
    return $this;
  }

  public function addConditionGroup(ConditionGroup $conditionGroup) : Query
  {
    $this->conditionGroups[] = $conditionGroup;
    return $this;
  }

  public function setLimit(int $limit) : Query
  {
    $this->rangeStart = 0;
    $this->rangeLength = $limit;
    return $this;
  }

  public function hasLimit() : bool
  {
    return !empty($this->rangeLength);
  }

  public function setConditionLogic(string $conditionLogic) : Query
  {
    $this->conditionLogic = $conditionLogic;
    return $this;
  }

  public function setRange(int $start, int $length) : Query
  {
    $this->rangeStart = $start;
    $this->rangeLength = $length;
    return $this;
  }

  public function addSortOrder(Order $order) : Query
  {
    $this->sortOrders[$order->fieldName] = $order;
    return $this;
  }

  public function hasSortOrderForField(string $fieldName) : bool
  {
    return array_key_exists($fieldName, $this->sortOrders);
  }

  public function clearSortOrders() : Query
  {
    $this->sortOrders = [];
    return $this;
  }

  public function getQuery() : QueryInterface
  {
    $query = $this->getBaseQuery();

    // add ranges and limits if needed
    if(!empty($this->rangeLength))
    {
      $query->range($this->rangeStart, $this->rangeLength);
    }

    return $query;
  }

  public function getTotalCountQuery() : QueryInterface
  {
    $query = $this->getBaseQuery();
    $query->count();
    return $query;
  }

  private function getBaseQuery() : QueryInterface
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
      $conditionGroup = new ConditionGroup();
      $conditionGroup->setLogic($this->conditionLogic);

      foreach($this->conditions as $condition)
      {
        $conditionGroup->addCondition($condition);
      }

      $conditionGroup->applyConditionsOnQuery($query);
    }

    // Next the possible added condition groups
    foreach($this->conditionGroups as $conditionGroup)
    {
      $conditionGroup->applyConditionsOnQuery($query);
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

  public function fetch() : array
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

  public function fetchIds() : array
  {
    $query = $this->getQuery();
    $result = $query->execute();

    return empty($result) ? [] : $result;
  }

  public function fetchId() : ?string
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

  public function fetchTotalCount() : int
  {
    $query = $this->getTotalCountQuery();
    $result = $query->execute();

    return $result;
  }
}
