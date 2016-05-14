<?php
namespace Drupal\spectrum\Model;

abstract class Relationship
{
	public $modelType;
	public $relationshipName;

	public function __construct($relationshipName, $modelType)
	{
		$this->relationshipName = $relationshipName;
    $this->modelType = $modelType;
	}

	public abstract function getCondition();
}
