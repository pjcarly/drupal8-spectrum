<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

/**
 * This class is used just to pass an AccessPolicy entry around in the application
 */
class AccessPolicyEntity
{
  /**
   * @var string
   */
  protected $entityType;

  /**
   * @var int
   */
  protected $entityId;

  /**
   * @var int
   */
  protected $userId;


  public function __construct(string $entityType, int $entityId, int $userId)
  {
    $this->entityType = $entityType;
    $this->entityId = $entityId;
    $this->userId = $userId;
  }

  /**
   * Sets the Entity Type
   *
   * @param string $entityType
   * @return AccessPolicyEntity
   */
  public function setEntityType(string $entityType): AccessPolicyEntity
  {
    $this->entityType = $entityType;
    return $this;
  }

  /**
   * Returns the entity type
   *
   * @return string
   */
  public function getEntityType(): string
  {
    return $this->entityType;
  }

  /**
   * Sets the Entity ID
   *
   * @param int $entityId
   * @return AccessPolicyEntity
   */
  public function setEntityId(int $entityId): AccessPolicyEntity
  {
    $this->entityId = $entityId;
    return $this;
  }

  /**
   * Returns the entity id
   *
   * @return int
   */
  public function getEntityId(): int
  {
    return $this->entityId;
  }

  /**
   * Sets the User ID
   *
   * @param int $userId
   * @return AccessPolicyEntity
   */
  public function setUserId(int $userId): AccessPolicyEntity
  {
    $this->userId = $userId;
    return $this;
  }

  /**
   * Returns the user id
   *
   * @return int
   */
  public function getUserId(): int
  {
    return $this->userId;
  }

  /**
   * Returns an Array of the values of this Entity, that can be used in an InsertQuery
   *
   * @return array
   */
  public function getInsertValue(): array
  {
    return [
      'entity_type' => $this->getEntityType(),
      'entity_id' => $this->getEntityId(),
      'uid' => $this->getUserId(),
    ];
  }
}
