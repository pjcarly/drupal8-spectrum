<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\Condition;

class FieldRelationship extends Relationship
{
	public $relationshipField;
  public $modelType;
  public $isPolymorphic = false;

  private $firstModelType;
  public $fieldCardinality; // cardinality is the maximum number of references allowed for the field.

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
    $modelType = $this->firstModelType;
    if($this->isPolymorphic)
    {
      return $modelType::getEntityQuery();
    }
    else
    {
      return $modelType::getModelQuery();
    }
  }

  public function setRelationshipMetaData()
  {
    // First we will get the field Definition to read our meta data from
    $relationshipSource = $this->relationshipSource;
    $fieldDefinition = $relationshipSource::getFieldDefinition($this->getField());
    $fieldSettings = $fieldDefinition->getItemDefinition()->getSettings();

    // Here we decide if our relationship is polymorphic or for a single entity/bundle type
    $relationshipEntityType = $fieldSettings['target_type'];
    $relationshipBundle = array_shift($fieldSettings['handler_settings']['target_bundles']);
    $this->firstModelType = Model::getModelClassForEntityAndBundle($relationshipEntityType, $relationshipBundle);

    if(sizeof($fieldSettings['handler_settings']['target_bundles']) > 1)
    {
      $this->isPolymorphic = true;
      $this->modelType = null;
    }
    else
    {
      $this->modelType = $this->firstModelType;
    }

    // Next we set the cardinality of the field, either we have a single reference or multiple references (single parent / multiple parents)
    $this->fieldCardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();
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

  // lets define magic getters for ease of access
  public function __get($property)
	{
		if (property_exists($this, $property))
		{
			return $this->$property;
		}
		else // lets check for pseudo properties
		{
			switch($property)
			{
				case "column":
					return $this->getColumn();
				  break;
				case "field":
					return $this->getField();
				   break;
				case "isSingle":
					return $this->fieldCardinality === 1;
          break;
        case "isMultiple":
          return $this->fieldCardinality !== 1;
          break;
        case "isUnlimited":
          return $this->fieldCardinality === -1;
				  break;
			}
		}
	}
}