<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\EntityQuery;
use Drupal\spectrum\Query\Condition;

/**
 * A ReferencedRelationship is a relationship that is the inverse of a FieldRelationship. It doesnt exist in a column on the Entity it is provided.
 * It is merely a way of giving an inverse relationship a name, and doing saves, fetches, and validations for every Model in the Relationship.
 * The relationship isnt a column in the database
 *
 * A ReferencedRelationship cannot exist alone, it must be accomanied by its inverse FieldRelationship on the Referring Model
 */
class ReferencedRelationship extends Relationship
{
  /**
   * The inverse FieldRelationship, this is looked up at construction time
   *
   * @var FieldRelationship
   */
  public $fieldRelationship;

  /**
   *
   *
   * @var string
   */
  public $fieldRelationshipName;

  /**
   * @param string $relationshipName The name of your relationship
   * @param string $modelType The fully qualified classname of the model you wish to relate. A lookup will be done in the modelservice to find the registered modelclass for then entity/bundle combination, as it might be overridden.
   * @param string $fieldRelationshipName The relationshipName of the Field Relationship on the inverse Model
   * @param integer $cascade (Optional) an indicator whether this relationship should be casading delete or not (default false), statics available: NO_CASCADE, CASCADE_ON_DELETE
   */
  public function __construct(string $relationshipName, string $modelType, string $fieldRelationshipName, int $cascade = 0)
  {
    parent::__construct($relationshipName, $cascade);
    $this->modelType = Model::getRegisteredModelTypeForModelType($modelType);
    $this->fieldRelationshipName = $fieldRelationshipName;
    $this->fieldRelationship = $this->modelType::getRelationship($fieldRelationshipName);
  }

  /**
   * Returns a Spectrum Query Condition with the correct relationship field and column filled in.
   *
   * @return Condition
   */
  public function getCondition() : Condition
  {
    return new Condition($this->fieldRelationship->relationshipField, 'IN', null);
  }

  /**
   * Returns a ModelQuery with the same type as the Relationship
   *
   * @return ModelQuery
   */
  public function getRelationshipQuery() : EntityQuery
  {
    $modelType = $this->modelType;
    return $modelType::getModelQuery();
  }
}
