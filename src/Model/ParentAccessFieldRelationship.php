<?php

namespace Drupal\spectrum\Model;

/**
 * A Parent Access Field Relationship is a Field Relationship that also indicates what Parent should be used for the access Policy
 * If the user has access to the parent (the one defined in this relationship), the user should also have access to the child (the model where the relationship is defined)
 */
class ParentAccessFieldRelationship extends FieldRelationship
{
  protected $parentPriority;

  /**
   * FieldRelationship constructor.
   *
   * @param string $relationshipName the name you want to give to your relationship
   * @param string $relationshipField The fieldname of the drupal entity that stores this relationship, should be `field.column` for example `field_user.target_id`
   * @param int $cascade the Cascade indicator (NO_CASCADE, and CASCADE_ON_DELETE are available as static ints on Relationship)
   * @param int $parentPriority Defines the Priority in which the Private Access Policy should decide to calculate the access
   */
  public function __construct(
    string $relationshipName,
    string $relationshipField,
    int $cascade = 0,
    int $parentPriority = 0
  ) {
    parent::__construct($relationshipName, $relationshipField, $cascade);
    $this->parentPriority = $parentPriority;
  }

  /**
   * @return int
   */
  public function getParentPriority(): ?int
  {
    return $this->parentPriority;
  }

  /**
   * Sets the parent priority
   *
   * @param integer|null $value
   * @return ParentAccessFieldRelationship
   */
  public function setParentPriority(?int $value): ParentAccessFieldRelationship
  {
    $this->parentPriority = $value;
    return $this;
  }
}
