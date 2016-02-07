<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Condition;

class ParentRelationship extends Relationship
{
	public $relationshipField;

	public function __construct($relationshipName, $modelType, $relationshipField)
	{
		parent::__construct($relationshipName, $modelType);
		$this->relationshipField = $relationshipField;
	}

	public function getCondition()
	{
		$modelType = $this->modelType;
		return new Condition($modelType::$idField, 'IN', null);
	}

	public function getField()
	{
		$positionOfDot = strpos($this->relationshipField, '.');
		return substr($this->relationshipField, 0, $positionOfDot);
	}

	public function getColumn()
	{
		$positionOfDot = strpos($this->relationshipField, '.');
		return substr($this->relationshipField, $positionOfDot + 1); // exclude the "." so +1
	}
}
