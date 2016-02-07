<?php
namespace Drupal\spectrum\Model;

abstract class Relationship
{
	public $modelType;
	public $relationshipName;

	public function __construct($relationshipName, $modelType)
	{
		$this->modelType = $modelType;
		$this->relationshipName = $relationshipName;
	}

	public abstract function getCondition();
}
