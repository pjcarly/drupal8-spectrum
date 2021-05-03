<?php

namespace Drupal\spectrum\Query;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;

abstract class QueryBase
{
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
  protected $conditions = [];

  /**
   * Here the ConditionGroups are stored, the condition groups will be applied in the order you add them.
   *
   * @var ConditionGroup[]
   */
  protected $conditionGroups = [];

  /**
   * Here the Query/Order are stored, the orders will be applied in the order you add them
   *
   * @var Order[]
   */
  protected $sortOrders = [];

  /**
   * Potential Drupal tag you want to add to the query
   *
   * @var string
   */
  protected $tag;

  /**
   * The logic that will be applied to the conditions (not baseConditions, and not ConditionGroups)
   *
   * @var string
   */
  protected $conditionLogic;

  /**
   * The entity type you want to query
   *
   * @var string
   */
  protected $entityType;

  /**
   * @var AccessPolicyInterface|null
   *   Indicates whether to use Spectrum Access Policy.
   */
  protected $accessPolicy;

  /**
   * Custom userId to use for access policy query
   *
   * @var int|null
   */
  protected $userIdForAccessPolicy;

  /**
   * @param string $entityType The entity type you want to query
   */
  public function __construct(string $entityType)
  {
    $this->entityType = $entityType;
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
      $userId = $this->getUserIdForAccessPolicy() ?? \Drupal::currentUser()->id();
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
    return $this->userIdForAccessPolicy ?? \Drupal::currentUser()->id();
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
}
