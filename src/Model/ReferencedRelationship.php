<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Condition;

class ReferencedRelationship extends Relationship
{
	public $fieldRelationship;
	public $fieldRelationshipName;
  public $modelType;

	public function __construct($relationshipName, $modelType, $fieldRelationshipName)
	{
		parent::__construct($relationshipName);
    $this->modelType = $modelType;
		$this->fieldRelationshipName = $fieldRelationshipName;
		$this->fieldRelationship = $modelType::getRelationship($fieldRelationshipName);
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
}
