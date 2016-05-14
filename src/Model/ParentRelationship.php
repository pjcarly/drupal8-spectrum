<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Condition;

class ParentRelationship extends Relationship
{
	public $relationshipField;
  public $modelType;

	public function __construct($relationshipName, $relationshipField)
	{
		parent::__construct($relationshipName);
		$this->relationshipField = $relationshipField;
	}

	public function getCondition()
	{
		$modelType = $this->modelType;
		return new Condition($modelType::$idField, 'IN', null);
	}

  public function getRelationshipQuery()
  {
    $modelType = $this->modelType;
    return $modelType::getModelQuery();
  }

  public function setModelType()
  {
    $relationshipSource = $this->relationshipSource;
    $fieldDefinition = $relationshipSource::getFieldDefinition($this->getField());
    $fieldSettings = $fieldDefinition->getItemDefinition()->getSettings();

    $relationshipEntityType = $fieldSettings['target_type'];
    $relationshipBundle = array_shift($fieldSettings['handler_settings']['target_bundles']); // TODO: polymorphic relationships

    $this->modelType = Model::getModelClassForEntityAndBundle($relationshipEntityType, $relationshipBundle);
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
