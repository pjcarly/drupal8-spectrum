<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Condition;

class ChildRelationship extends Relationship
{
	public $parentRelationship;
	public $parentRelationshipName;

	public function __construct($relationshipName, $modelType, $parentRelationshipName)
	{
		parent::__construct($relationshipName, $modelType);
		$this->parentRelationshipName = $parentRelationshipName;
		$this->parentRelationship = $modelType::getRelationship($parentRelationshipName);
	}

	public function getCondition()
	{
		$parentRelationship = $this->parentRelationship;

		return new Condition($parentRelationship->relationshipField, 'IN', null);
	}

  public function getRelationshipQuery()
  {

  }
}
