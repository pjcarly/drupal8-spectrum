<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

class ReferencedRelationship extends Relationship
{
  protected static $cachedModelTypes = [];

  public $fieldRelationship;
  public $fieldRelationshipName;
  public $modelType;

  public function __construct($relationshipName, $modelType, $fieldRelationshipName, $cascade = 0)
  {
    parent::__construct($relationshipName, $cascade);
    $this->modelType = static::getModelType($modelType);
    $this->fieldRelationshipName = $fieldRelationshipName;
    $this->fieldRelationship = $this->modelType::getRelationship($fieldRelationshipName);
  }

  public function getCondition() : Condition
  {
    return new Condition($this->fieldRelationship->relationshipField, 'IN', null);
  }

  public function getRelationshipQuery() : ModelQuery
  {
    $modelType = $this->modelType;
    return $modelType::getModelQuery();
  }

  /**
   * Because modeltypes can be exteded in each application, we load the modelType based on the one available in the model service
   */
  public static function getModelType(string $modelType) : string
  {
    if(!array_key_exists($modelType, static::$cachedModelTypes))
    {
      static::$cachedModelTypes[$modelType] = Model::getModelClassForEntityAndBundle($modelType::$entityType, $modelType::$bundle);
    }

    return static::$cachedModelTypes[$modelType];
  }
}
