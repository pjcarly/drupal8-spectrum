<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Condition;

class ReferencedRelationship extends Relationship
{
  public static $NO_CASCADE = 0;
  public static $CASCADE_ON_DELETE = 1;

  public $fieldRelationship;
  public $fieldRelationshipName;
  public $modelType;

  public $cascadingDelete = false;

  public function __construct($relationshipName, $modelType, $fieldRelationshipName, $cascade = 0)
  {
    parent::__construct($relationshipName);
    $this->modelType = $modelType;
    $this->fieldRelationshipName = $fieldRelationshipName;
    $this->fieldRelationship = $modelType::getRelationship($fieldRelationshipName);
    $this->setCascadingDelete($cascade === 1);
  }

  public function getCondition()
  {
    return new Condition($this->fieldRelationship->relationshipField, 'IN', null);
  }

  public function getRelationshipQuery()
  {
    $modelType = $this->modelType;
    return $modelType::getModelQuery();
  }

  public function setCascadingDelete($cascadingDelete)
  {
    $this->cascadingDelete = $cascadingDelete;
    return $this; // to enable
  }
}
