<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Database\Query\Condition as DrupalCondition;
use Drupal\Core\Database\Query\Select as DrupalSelectQuery;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\spectrum\Runnable\BatchableInterface;

/**
 * This class provides base functionality for different query types
 */
abstract class Query implements BatchableInterface
{
  /**
   * This variable stores the batch size, it is used by the BatchableInterface to process batches of queries
   *
   * @var int
   */
  protected $batchSize;

  /**
   * Here we store all the ids the batch must return, we must page over them during every batch cycle
   *
   * @var array
   */
  protected $batchIds = [];

  /**
   * Here we store the current page we are on in our batch cycle
   *
   * @var int
   */
  protected $batchPage;

  /**
   * This array holds the base conditions, no matter what, they will always be applied on the query, regardless of logic or implementation
   *
   * @var Condition[]
   */
  protected $baseConditions = [];

  /**
   * This holds all the Conditions on the query, and will be applied in the order you add them.
   *
   * @var Condition[]
   */
  public $conditions = [];

  /**
   * Here the ConditionGroups are stored, the condition groups will be applied in the order you add them.
   *
   * @var ConditionGroup[]
   */
  public $conditionGroups = [];

  /**
   * Here the Query/Order are stored, the orders will be applied in the order you add them
   *
   * @var Order[]
   */
  public $sortOrders = [];

  /**
   * Here you can find the expressions attached to this query. They will be added in the order you add them
   *
   * @var Expression[]
   */
  protected $expressions = [];

  /**
   * The start of the range you want to return
   *
   * @var int
   */
  public $rangeStart;

  /**
   * The length of the range you want to return
   *
   * @var int
   */
  public $rangeLength;

  /**
   * The logic that will be applied to the conditions (not baseConditions, and not ConditionGroups)
   *
   * @var string
   */
  public $conditionLogic;

  /**
   * Potential Drupal tag you want to add to the query
   *
   * @var string
   */
  public $tag;

  /**
   * The entity type you want to query
   *
   * @var string
   */
  protected $entityType;

  /**
   * @param string $entityType The entity type you want to query
   */
  public function __construct(string $entityType)
  {
    $this->entityType = $entityType;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalBatchedRecords() : ?int
  {
    return sizeof($this->batchIds);
  }

  /**
   * {@inheritdoc}
   */
  public function getNextBatch() : array
  {
    $this->batchPage++;

    // We get all the keys we need to handle, in an array with consecutive numbers starting at 0 as the key
    $keys = array_keys($this->batchIds);

    // Next we generate an array with consecutive numbers starting at the page we are currently handleing, and for the max range of the batchsize
    $range = range(($this->batchPage - 1) * $this->batchSize, ($this->batchPage * $this->batchSize) -1);
    $range = array_flip($range);

    // Next we generate the intersection, between the range and the keys, so we only have the keys that we need to handle in this batch
    $nextBatchIds = array_intersect_key($keys, $range);

    if(empty($nextBatchIds))
    {
      return [];
    }

    // Now we need to find the Id Field of the entity type, as this can be different per entity in Drupal
    $entityTypeManager = \Drupal::entityTypeManager();
    $entityTypeDefinition = $entityTypeManager->getDefinition($this->getEntityType());
    $idField = $entityTypeDefinition->getKeys()['id'];

    // Now we copy the current query, and add a condition for the IDS we need to handle
    $query = $this->copy();
    $query->addCondition(new Condition($idField, 'IN', array_values($nextBatchIds)));
    return $query->fetch();
  }

  /**
   * Sets the size of the batch, this is needed for BatchableInterface
   *
   * @param integer $batchSize
   * @return BatchableInterface
   */
  public function setBatchSize(int $batchSize) : BatchableInterface
  {
    $this->batchSize = $batchSize;
    $this->batchPage = 0;
    $this->batchIds = $this->fetchIds();

    return $this;
  }

  /**
   * Set a tag you want to add to the query
   *
   * @param string $tag
   * @return Query
   */
  public function setTag(string $tag) : Query
  {
    $this->tag = $tag;
    return $this;
  }

  /**
   * Add a Condition that will always be applied to the query, no matter what the logic is
   *
   * @param Condition $condition
   * @return Query
   */
  public function addBaseCondition(Condition $condition) : Query
  {
    $this->baseConditions[] = $condition;
    return $this;
  }

  /**
   * Add a condition to the query. If you set a conditionlogic, the numbers will be the order in which the conditions were added to the query
   *
   * @param Condition $condition
   * @return Query
   */
  public function addCondition(Condition $condition) : Query
  {
    $this->conditions[] = $condition;
    return $this;
  }

  /**
   * Add a Query/ConditionGroup, in case multiple groups are added and/or conditions, they will be combined through AND.
   *
   * @param ConditionGroup $conditionGroup
   * @return Query
   */
  public function addConditionGroup(ConditionGroup $conditionGroup) : Query
  {
    $this->conditionGroups[] = $conditionGroup;
    return $this;
  }

  /**
   * Adds an expression to the query, that can be used in a sort order
   *
   * @param Expression $expression
   * @return Query
   */
  public function addExpression(Expression $expression) : Query
  {
    $this->expressions[$expression->getName()] = $expression;
    return $this;
  }

  /**
   * Removes all the expressions from the Query
   *
   * @return Query
   */
  public function clearExpressions() : Query
  {
    $this->expressions = [];
    return $this;
  }

  /**
   * Sets the limit of amount of results you want to return, this will override any range that was previously set
   *
   * @param integer $limit
   * @return Query
   */
  public function setLimit(int $limit) : Query
  {
    $this->rangeStart = 0;
    $this->rangeLength = $limit;
    return $this;
  }

  /**
   * Checks if this query has a limit defined
   *
   * @return boolean
   */
  public function hasLimit() : bool
  {
    return !empty($this->rangeLength);
  }

  /**
   * Set the conditionlogic that needs to be appled to the conditions that were added. For example: "OR(1,2, AND(3,4, OR(1,5))"
   *
   * @param string $conditionLogic
   * @return Query
   */
  public function setConditionLogic(string $conditionLogic) : Query
  {
    $this->conditionLogic = $conditionLogic;
    return $this;
  }

  /**
   * Define a range for the results. this will override any limit that was previously set
   *
   * @param integer $start
   * @param integer $length
   * @return Query
   */
  public function setRange(int $start, int $length) : Query
  {
    $this->rangeStart = $start;
    $this->rangeLength = $length;
    return $this;
  }

  /**
   * Add a sortorder to the query, the orders will be applied in the order they were added
   *
   * @param Order $order
   * @return Query
   */
  public function addSortOrder(Order $order) : Query
  {
    $this->sortOrders[$order->fieldName] = $order;
    return $this;
  }

  /**
   * Check if a sort order exists for a certain field
   *
   * @param string $fieldName
   * @return boolean
   */
  public function hasSortOrderForField(string $fieldName) : bool
  {
    return array_key_exists($fieldName, $this->sortOrders);
  }

  /**
   * Remove all the sort orders from the query
   *
   * @return Query
   */
  public function clearSortOrders() : Query
  {
    $this->sortOrders = [];
    return $this;
  }

  /**
   * Return a DrupalQuery with all the conditions and other configurations applied to the query
   *
   * @return QueryInterface
   */
  public function getQuery() : QueryInterface
  {
    $query = $this->getBaseQuery();

    // add ranges and limits if needed
    if($this->hasLimit())
    {
      $query->range($this->rangeStart, $this->rangeLength);
    }

    return $query;
  }

  /**
   * Returns a Drupal Count Query with all the conditions and other configurations applied (except for the range or limit)
   *
   * @return QueryInterface
   */
  public function getTotalCountQuery() : QueryInterface
  {
    $query = $this->getBaseQuery();
    $query->count();
    return $query;
  }

  /**
   * Return a DrupalQuery with all the conditions and other configurations applied (except for the range or limit)
   *
   * @return QueryInterface
   */
  protected function getBaseQuery() : QueryInterface
  {
    // We abstracted the getQuery and getTotalCountQuery functions in this function, to avoid duplicate code
    $query = \Drupal::entityQuery($this->entityType);

    // next we check for conditions and add them if needed

    // Base conditions must always be applied, regardless of the logic
    foreach($this->baseConditions as $condition)
    {
      if(!$this->hasExpression($condition->getFieldName()))
      {
        $condition->addQueryCondition($query);
      }
    }

    // We might have a logic, lets check for it
    if(empty($this->conditionLogic))
    {
      foreach($this->conditions as $condition)
      {
        if(!$this->hasExpression($condition->getFieldName()))
        {
          $condition->addQueryCondition($query);
        }
      }
    }
    else
    {
      // A logic was provided, we add all the conditions on the query to a ConditionGroup
      // Apply the logic, and then add pass in the drupal query to apply the conditions with logic on.
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
      // We filter out any possible fieldname that was used in an expression
      if(!$this->hasExpression($sortOrder->getFieldName()))
      {
        $query->sort($sortOrder->getFieldName(), $sortOrder->getDirection(), $sortOrder->getLangcode());
      }
    }

    if(!empty($this->tag))
    {
      $query->addTag($this->tag);
    }

    if(!empty($this->expressions))
    {
      // Here we do some hackory to get Expressions working
      // We create a conditiongroup which contains a condition with all the fields of the expressions
      // This makes sure that there is a JOIN added for the fields needed
      // Then later in the Query alter hook, we remove this conditiongroup,
      // and parse the field names in the expression with the correct database column name
      $expressionConditionGroup = new ConditionGroup();

      $logic = [];
      foreach($this->expressions as $expression)
      {
        foreach($expression->getFields() as $field)
        {
          $logic[] = sizeof($logic)+1;
          $expressionConditionGroup->addCondition(new Condition($field, '=', '__pseudo_placeholder'));
        }
      }

      $expressionConditionGroup->setLogic('OR('.implode(',', $logic).')');
      $expressionConditionGroup->applyConditionsOnQuery($query);

      $query->addTag('spectrum_query')->addMetaData('spectrum_query', $this);
    }

    return $query;
  }

  /**
   * Execute the query, and return the entities in an array
   *
   * @return array
   */
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
      $store = \Drupal::entityTypeManager()->getStorage($this->entityType);
      return empty($ids) ? [] : $store->loadMultiple($ids);
    }
  }

  /**
   * Execute the query and return only the found ids in an array
   *
   * @return array
   */
  public function fetchIds() : array
  {
    $query = $this->getQuery();
    $result = $query->execute();

    return empty($result) ? [] : $result;
  }

  /**
   * Execute the query and return the first id of the result
   *
   * @return string|null
   */
  public function fetchId() : ?string
  {
    $ids = $this->fetchIds();

    return empty($ids) ? null : array_shift($ids);
  }

  /**
   * Execute the query, and return the first result entity
   *
   * @return EntityInterface|null
   */
  public function fetchSingle() : ?EntityInterface
  {
    $id = $this->fetchId();

    if($this->entityType === 'user')
    {
      // ugly fix for custom fields on User
      return empty($id) ? null : \Drupal\user\Entity\User::load($id);
    }
    else
    {
      $store = \Drupal::entityTypeManager()->getStorage($this->entityType);
      return empty($id) ? null : $store->load($id);
    }
  }

  /**
   * Fetch the total query for this query, ignoring the ranges or limits
   *
   * @return integer
   */
  public function fetchTotalCount() : int
  {
    $query = $this->getTotalCountQuery();
    $result = $query->execute();

    return $result;
  }

  /**
   * Get this holds all the Conditions on the query, and will be applied in the order you add them.
   *
   * @return  Condition[]
   */
  public function getConditions() : array
  {
    return $this->conditions;
  }

  /**
   * Get this array holds the base conditions, no matter what, they will always be applied on the query, regardless of logic or implementation
   *
   * @return  Condition[]
   */
  public function getBaseConditions() : array
  {
    return $this->baseConditions;
  }

  /**
   * Get here the ConditionGroups are stored, the condition groups will be applied in the order you add them.
   *
   * @return  ConditionGroup[]
   */
  public function getConditionGroups() : array
  {
    return $this->conditionGroups;
  }

  /**
   * Returns all the expressions of this query
   *
   * @return Expression[]
   */
  public function getExpressions() : array
  {
    return $this->expressions;
  }

  /**
   * Returns TRUE if this query has an expression with the provided name
   *
   * @param string $name
   * @return boolean
   */
  public function hasExpression(string $name) : bool
  {
    return array_key_exists($name, $this->expressions);
  }

  /**
   * This function will copy all the base conditions, conditions and condition groups from the provided query, into this query
   *
   * @param Query $query
   * @return Query
   */
  public function copyConditionsFrom(Query $query) : Query
  {
    foreach($query->getBaseConditions() as $baseCondition)
    {
      $this->addBaseCondition($baseCondition);
    }

    foreach($query->getConditions() as $condition)
    {
      $this->addCondition($condition);
    }

    foreach($query->getConditionGroups() as $conditionGroup)
    {
      $this->addConditionGroup($conditionGroup);
    }

    $conditionLogic = $query->getConditionLogic();
    if(!empty($conditionLogic))
    {
      $this->setConditionLogic($conditionLogic);
    }

    return $this;
  }

  public function copySortOrdersFrom(Query $query) : Query
  {
    foreach($query->getSortOrders() as $sortOrder)
    {
      $this->addSortOrder($sortOrder);
    }

    return $this;
  }

  /**
   * This function will return a copy of the current Query, it will be a new reference, with all the same Conditions, Orders, Ranges, ...
   *
   * @return Query
   */
  public function copy() : Query
  {
    $query = new EntityQuery($this->getEntityType()); // Doesnt matter what the subclass is, the conditions will be added below
    $query->copyConditionsFrom($this);
    $query->copySortOrdersFrom($this);

    $tag = $this->getTag();
    if(!empty($tag))
    {
      $query->setTag($tag);
    }

    if($this->hasLimit())
    {
      $query->setRange($this->getRangeStart(), $this->getRangeLength());
    }

    return $query;
  }

  /**
   * Returns all the sort orders in an array
   *
   * @return Order[]
   */
  public function getSortOrders() : array
  {
    return $this->sortOrders;
  }

  /**
   * Get potential Drupal tag you want to add to the query
   *
   * @return  string|null
   */
  public function getTag() : ?string
  {
    return $this->tag;
  }

  /**
   * Get the start of the range you want to return
   *
   * @return  int
   */
  public function getRangeStart() : int
  {
    return $this->rangeStart;
  }

  /**
   * Get the length of the range you want to return
   *
   * @return  int
   */
  public function getRangeLength() : int
  {
    return $this->rangeLength;
  }

  /**
   * Get the logic that will be applied to the conditions (not baseConditions, and not ConditionGroups)
   *
   * @return  string|null
   */
  public function getConditionLogic() : ?string
  {
    return $this->conditionLogic;
  }

  /**
   * Get the entity type you want to query
   *
   * @return  string
   */
  public function getEntityType() : string
  {
    return $this->entityType;
  }

  /**
   * This function parses the Expressions into the Drupal Select Query.
   * When an expression is added to a spectrum query, it isn't added as a sort
   * order at first. Instead it is ignored, and later added through a
   * alter_query hook. This function is called through the hook, and parses the
   * expression in the query.
   *
   * @param DrupalSelectQuery $drupalQuery
   * @return Query
   */
  public function parseExpressions(DrupalSelectQuery $drupalQuery) : Query
  {
    $index = 0;
    $columnMapping = [];
    $pseudoConditionGroupKey = null;

    // First we find the column mapping from the drupal query and the key to unset
    foreach($drupalQuery->conditions() as $key => $condition)
    {
      if($condition['field'] instanceof DrupalCondition)
      {
        /** @var DrupalCondition $conditionGroup */
        $conditionGroup = $condition['field'];

        foreach($conditionGroup->conditions() as $subCondition)
        {
          if($subCondition['value'] === '__pseudo_placeholder')
          {
            $pseudoConditionGroupKey = $key;
            $columnMapping[$index] = $subCondition['field'];
            $index++;
          }
        }
      }
    }

    // We unset the conditiongroup
    unset($drupalQuery->conditions()[$pseudoConditionGroupKey]);

    // Now we have the initial colums, we match those with the fields in the expressions
    $index = 0;
    foreach($this->expressions as $expression)
    {
      $expressionString = $expression->getExpression();
      foreach($expression->getFields() as $field)
      {
        $column = $columnMapping[$index];
        $expressionString = str_replace($field, $column, $expressionString);

        $index++;
      }

      $drupalQuery->addExpression($expressionString, $expression->getName());
    }

    // And now we add the conditions and sort orders from the expression
    foreach($this->sortOrders as $sortOrder)
    {
      if($this->hasExpression($sortOrder->getFieldName()))
      {
        $drupalQuery->orderBy($sortOrder->getFieldName(), $sortOrder->getDirection());
      }
    }

    return $this;
  }

}
