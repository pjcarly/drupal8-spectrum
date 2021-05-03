<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Database\Query\Condition as DrupalCondition;
use Drupal\Core\Database\Query\Select as DrupalSelectQuery;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;

abstract class QueryBase
{
  private AccountProxyInterface $currentUser;

  /**
   * This array holds the base conditions, no matter what, they will always be applied on the query, regardless of logic or implementation
   *
   * @var Condition[]
   */
  protected array $baseConditions = [];

  /**
   * This holds all the Conditions on the query, and will be applied in the order you add them.
   *
   * @var Condition[]
   */
  protected array $conditions = [];

  /**
   * Here the ConditionGroups are stored, the condition groups will be applied in the order you add them.
   *
   * @var ConditionGroup[]
   */
  protected array $conditionGroups = [];

  /**
   * Here the Query/Order are stored, the orders will be applied in the order you add them
   *
   * @var Order[]
   */
  protected array $sortOrders = [];

  /**
   * Potential Drupal tag you want to add to the query
   *
   * @var string
   */
  protected string $tag;

  /**
   * The logic that will be applied to the conditions (not baseConditions, and not ConditionGroups)
   *
   * @var string
   */
  protected string $conditionLogic;

  /**
   * The entity type you want to query
   *
   * @var string
   */
  protected string $entityType;

  /**
   * @var AccessPolicyInterface|null
   *   Indicates whether to use Spectrum Access Policy.
   */
  protected ?AccessPolicyInterface $accessPolicy = null;

  /**
   * Custom userId to use for access policy query
   *
   * @var int|null
   */
  protected ?int $userIdForAccessPolicy = null;

  /**
   * Here you can find the expressions attached to this query. They will be added in the order you add them
   *
   * @var Expression[]
   */
  protected array $expressions = [];

  /**
   * Flag to indicate if there are expressions in sort orders
   * this will be used pure internally, and is needed because instances
   * of this query are passed around in drupal 
   * We need this in getBaseQuery and parseExpressions
   *
   * @var boolean
   */
  private $expressionsInSortOrders = false;

  /**
   * @param string $entityType The entity type you want to query
   */
  public function __construct(string $entityType)
  {
    $this->entityType = $entityType;
    $this->currentUser = \Drupal::service('current_user');
  }

  protected abstract function getDrupalQuery(): QueryInterface;

  /**
   * Return a DrupalQuery with all the conditions and other configurations applied (except for the range or limit)
   *
   * @return QueryInterface
   */
  protected function getBaseQuery(): QueryInterface
  {
    // We abstracted the getQuery and getTotalCountQuery functions in this function, to avoid duplicate code
    $drupalQuery = $this->getDrupalQuery();

    // next we check for conditions and add them if needed

    // Base conditions must always be applied, regardless of the logic
    foreach ($this->baseConditions as $condition) {
      $condition->addQueryCondition($drupalQuery, $drupalQuery);
    }

    // We might have a logic, lets check for it
    if (empty($this->conditionLogic)) {
      foreach ($this->conditions as $condition) {
        $condition->addQueryCondition($drupalQuery, $drupalQuery);
      }
    } else {
      // A logic was provided, we add all the conditions on the query to a ConditionGroup
      // Apply the logic, and then pass in the drupal query to apply the conditions with logic on.
      $conditionGroup = new ConditionGroup();
      $conditionGroup->setLogic($this->conditionLogic);

      foreach ($this->conditions as $condition) {
        $conditionGroup->addCondition($condition);
      }

      $conditionGroup->applyConditionsOnQuery($drupalQuery);
    }

    // Next the possible added condition groups
    foreach ($this->conditionGroups as $conditionGroup) {
      $conditionGroup->applyConditionsOnQuery($drupalQuery);
    }

    $this->prepareSortOrders($drupalQuery);

    if (!empty($this->tag)) {
      $drupalQuery->addTag($this->tag);
    }

    if (!empty($this->expressions)) {
      // Here we do some hackory to get Expressions working
      // We create a conditiongroup which contains a condition with all the fields of the expressions
      // This makes sure that there is a JOIN added for the fields needed
      // Then later in the Query alter hook, we remove this conditiongroup,
      // and parse the field names in the expression with the correct database column name
      $expressionConditionGroup = new ConditionGroup();

      $logic = [];
      foreach ($this->expressions as $expression) {
        foreach ($expression->getFields() as $field) {
          $logic[] = sizeof($logic) + 1;
          $expressionConditionGroup->addCondition(new Condition($field, '=', '__pseudo_placeholder'));
        }
      }

      $expressionConditionGroup->setLogic('OR(' . implode(',', $logic) . ')');
      $expressionConditionGroup->applyConditionsOnQuery($drupalQuery);

      $drupalQuery->addTag('spectrum_query')->addMetaData('spectrum_query', $this);
    }

    if ($this->accessPolicy) {
      $drupalQuery->addTag('spectrum_query_use_access_policy');
      $drupalQuery->addMetaData('spectrum_query', $this);
    }

    return $drupalQuery;
  }

  private function prepareSortOrders(QueryInterface $drupalQuery): self
  {
    // and finally apply an order if needed
    foreach ($this->getSortOrders() as $sortOrder) {
      // We filter out any possible fieldname that was used in an expression
      if ($this->hasExpression($sortOrder->getFieldName())) {
        $this->expressionsInSortOrders = true;
        break;
      }
    }

    if (!$this->expressionsInSortOrders) {
      foreach ($this->getSortOrders() as $sortOrder) {
        $drupalQuery->sort($sortOrder->getFieldName(), $sortOrder->getDirection(), $sortOrder->getLangcode());
      }
    }

    return $this;
  }

  /**
   * Set a tag you want to add to the query
   *
   * @param string $tag
   * @return self
   */
  public function setTag(string $tag): self
  {
    $this->tag = $tag;
    return $this;
  }

  /**
   * Add a Condition that will always be applied to the query, no matter what the logic is
   *
   * @param Condition $condition
   * @return self
   */
  public function addBaseCondition(Condition $condition): self
  {
    $this->baseConditions[] = $condition;
    return $this;
  }

  /**
   * Add a condition to the query. If you set a conditionlogic, the numbers will be the order in which the conditions were added to the query
   *
   * @param Condition $condition
   * @return self
   */
  public function addCondition(Condition $condition): self
  {
    $this->conditions[] = $condition;
    return $this;
  }

  /**
   * Add a Query/ConditionGroup, in case multiple groups are added and/or conditions, they will be combined through AND.
   *
   * @param ConditionGroup $conditionGroup
   * @return self
   */
  public function addConditionGroup(ConditionGroup $conditionGroup): self
  {
    $this->conditionGroups[] = $conditionGroup;
    return $this;
  }

  /**
   * Set the conditionlogic that needs to be appled to the conditions that were added. For example: "OR(1,2, AND(3,4, OR(1,5))"
   *
   * @param string $conditionLogic
   * @return self
   */
  public function setConditionLogic(string $conditionLogic): self
  {
    $this->conditionLogic = $conditionLogic;
    return $this;
  }

  /**
   * Add a sortorder to the query, the orders will be applied in the order they were added
   *
   * @param Order $order
   * @return self
   */
  public function addSortOrder(Order $order): self
  {
    $this->sortOrders[$order->getFieldName()] = $order;
    return $this;
  }

  /**
   * Check if a sort order exists for a certain field
   *
   * @param string $fieldName
   * @return boolean
   */
  public function hasSortOrderForField(string $fieldName): bool
  {
    return array_key_exists($fieldName, $this->sortOrders);
  }

  /**
   * Remove all the sort orders from the query
   *
   * @return self
   */
  public function clearSortOrders(): self
  {
    $this->sortOrders = [];
    return $this;
  }

  /**
   * Return a DrupalQuery with all the conditions and other configurations applied to the query
   *
   * @return QueryInterface
   */
  public abstract function getQuery(): QueryInterface;

  /**
   * Get this holds all the Conditions on the query, and will be applied in the order you add them.
   *
   * @return  Condition[]
   */
  public function getConditions(): array
  {
    return $this->conditions;
  }

  /**
   * Get this array holds the base conditions, no matter what, they will always be applied on the query, regardless of logic or implementation
   *
   * @return  Condition[]
   */
  public function getBaseConditions(): array
  {
    return $this->baseConditions;
  }

  /**
   * Get here the ConditionGroups are stored, the condition groups will be applied in the order you add them.
   *
   * @return  ConditionGroup[]
   */
  public function getConditionGroups(): array
  {
    return $this->conditionGroups;
  }

  /**
   * Copy all the sorters from another query to this one
   *
   * @param QueryBase $query
   * @return self
   */
  public function copySortOrdersFrom(QueryBase $query): self
  {
    foreach ($query->getSortOrders() as $sortOrder) {
      $this->addSortOrder($sortOrder);
    }

    return $this;
  }

  /**
   * This function will copy all the base conditions, conditions and condition groups from the provided query, into this query
   *
   * @param QueryBase $query
   * @return self
   */
  public function copyConditionsFrom(QueryBase $query): self
  {
    foreach ($query->getBaseConditions() as $baseCondition) {
      $this->addBaseCondition($baseCondition);
    }

    foreach ($query->getConditions() as $condition) {
      $this->addCondition($condition);
    }

    foreach ($query->getConditionGroups() as $conditionGroup) {
      $this->addConditionGroup($conditionGroup);
    }

    $conditionLogic = $query->getConditionLogic();
    if (!empty($conditionLogic)) {
      $this->setConditionLogic($conditionLogic);
    }

    return $this;
  }

  /**
   * Returns all the sort orders in an array
   *
   * @return Order[]
   */
  public function getSortOrders(): array
  {
    return $this->sortOrders;
  }

  /**
   * Get potential Drupal tag you want to add to the query
   *
   * @return  string|null
   */
  public function getTag(): ?string
  {
    return $this->tag;
  }

  /**
   * Get the logic that will be applied to the conditions (not baseConditions, and not ConditionGroups)
   *
   * @return  string|null
   */
  public function getConditionLogic(): ?string
  {
    return $this->conditionLogic;
  }

  /**
   * Get the entity type you want to query
   *
   * @return  string
   */
  public function getEntityType(): string
  {
    return $this->entityType;
  }

  /**
   * @param AccessPolicyInterface|null $accessPolicy
   *
   * @return self
   */
  public function setAccessPolicy(?AccessPolicyInterface $accessPolicy): self
  {
    $this->accessPolicy = $accessPolicy;
    return $this;
  }

  /**
   * @return AccessPolicyInterface|null
   */
  public function getAccessPolicy(): ?AccessPolicyInterface
  {
    return $this->accessPolicy;
  }

  /**
   * @param AlterableInterface $query
   */
  public function executeAccessPolicy(AlterableInterface $query)
  {
    if ($this->accessPolicy) {
      $userId = $this->getUserIdForAccessPolicy() ?? $this->currentUser->id();
      $this->accessPolicy->onQuery($query, $userId);
    }
  }

  /**
   * Remove the accesspolicy to use this query with
   *
   * @return self
   */
  public function clearAccessPolicy(): self
  {
    $this->accessPolicy = null;
    return $this;
  }

  /**
   * Sets a custom userId to use for the Access Policy
   *
   * @param integer $userId
   * @return self
   */
  public function setUserIdForAccessPolicy(?int $userId): self
  {
    $this->userIdForAccessPolicy = $userId;
    return $this;
  }

  /**
   * Returns the UserId that is going to be used when this query should be executed with an access policy
   * If no custom userId is set, the loggedInUser will be returned
   *
   * @return integer
   */
  public function getUserIdForAccessPolicy(): ?int
  {
    return $this->userIdForAccessPolicy ?? $this->currentUser->id();
  }

  /**
   * Removes the custom userIdForAccess policy, instead the loggedInUser will be used by default
   *
   * @return self
   */
  public function clearUserIdForAccessPolicy(): self
  {
    unset($this->userIdForAccessPolicy);
    return $this;
  }

  /**
   * Adds an expression to the query, these can be used in an sort order or grouping
   *
   * @param Expression $expression
   * @return self
   */
  public function addExpression(Expression $expression): self
  {
    $this->expressions[$expression->getName()] = $expression;
    return $this;
  }

  /**
   * Removes all the expressions from the Query
   *
   * @return self
   */
  public function clearExpressions(): self
  {
    $this->expressions = [];
    return $this;
  }

  /**
   * Returns all the expressions of this query
   *
   * @return Expression[]
   */
  public function getExpressions(): array
  {
    return $this->expressions;
  }

  /**
   * Returns TRUE if this query has an expression with the provided name
   *
   * @param string $name
   * @return boolean
   */
  public function hasExpression(string $name): bool
  {
    return array_key_exists($name, $this->expressions);
  }

  /**
   * This function parses the Expressions into the Drupal Select Query.
   * When an expression is added to a spectrum query, it isn't added as a sort
   * order at first. Instead it is ignored, and later added through a
   * alter_query hook. This function is called through the hook, and parses the
   * expression in the query.
   *
   * @param DrupalSelectQuery $drupalQuery
   * @return self
   */
  public function parseExpressions(DrupalSelectQuery $drupalQuery): self
  {
    $index = 0;
    $columnMapping = [];
    $pseudoConditionGroupKey = null;

    // First we find the column mapping from the drupal query and the key to unset
    foreach ($drupalQuery->conditions() as $key => $condition) {
      if (is_array($condition) && $condition['field'] instanceof DrupalCondition) {
        /** @var DrupalCondition $conditionGroup */
        $conditionGroup = $condition['field'];

        foreach ($conditionGroup->conditions() as $subCondition) {
          if (is_array($subCondition) && $subCondition['value'] === '__pseudo_placeholder') {
            $pseudoConditionGroupKey = $key;
            // Also record the parsed column name, so we know what column to use in the expression later
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
    foreach ($this->expressions as $expression) {
      $expressionString = $expression->getExpression();
      foreach ($expression->getFields() as $field) {
        $column = $columnMapping[$index];
        $expressionString = str_replace($field, $column, $expressionString);

        $index++;
      }

      $drupalQuery->addExpression($expressionString, $expression->getName());
    }

    // And now we add the conditions and sort orders from the expression
    if ($this->expressionsInSortOrders) {
      foreach ($this->getSortOrders() as $sortOrder) {
        $drupalQuery->orderBy($sortOrder->getFieldName(), $sortOrder->getDirection());
      }
    }

    return $this;
  }
}
