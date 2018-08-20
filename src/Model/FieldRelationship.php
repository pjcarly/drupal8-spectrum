<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\EntityQuery;
use Drupal\spectrum\Query\Condition;

/**
 * A Field Relationship is a relationship that has a column on the enitty you're defining the relationship on.
 * It adds a foreign key with the ID of another entity
 */
class FieldRelationship extends Relationship
{
  /**
   * The name of the relationship field on the entity
   *
   * @var string
   */
  public $relationshipField;

  /**
   * Indicator whether this relationship is polymorphic (multiple bundles allowed) or not
   *
   * @var boolean
   */
  public $isPolymorphic = false;

  /**
   * An array containing the list of fully qualified classnames of all the polymoprhic model types
   *
   * @var array
   */
  public $polymorphicModelTypes = [];

  /**
   * THe first fully qualified classname of the model in a polymorphic fieldrelationship
   *
   * @var string
   */
  private $firstModelType;

  /**
   * Cardinality is the maximum number of references allowed for the field.
   *
   * @var int
   */
  public $fieldCardinality;

  /**
   *
   * @param string $relationshipName
   * @param string $relationshipField
   * @param integer $cascade
   */
  public function __construct(string $relationshipName, string $relationshipField, int $cascade = 0)
  {
    parent::__construct($relationshipName, $cascade);
    $this->relationshipField = $relationshipField;
  }

  /**
   * Returns a Condition with the relationship modeltype idfield already filled in as the field
   *
   * @return Condition
   */
  public function getCondition() : Condition
  {
    $modelType = $this->firstModelType;
    return new Condition($modelType::$idField, 'IN', null);
  }

  /**
   * Returns an EntityQuery for the relationship
   *
   * @return EntityQuery
   */
  public function getRelationshipQuery() : EntityQuery
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

  /**
   * Set the metadata of the relationship from the Drupal Field definitions, metadata includes the types (whether the relationship is polymorphic or not)
   * The fieldCardinality (whether 1 or multiple values can be set on the relationship), the entitytype and bundle
   *
   * @return void
   */
  protected function setRelationshipMetaData() : void
  {
    // First we will get the field Definition to read our meta data from
    $relationshipSource = $this->relationshipSource;
    $fieldDefinition = $relationshipSource::getFieldDefinition($this->getField());
    if(empty($fieldDefinition))
    {
      throw new \Drupal\spectrum\Exceptions\InvalidFieldException('Field '.$this->getField().' not found on modeltype: '.$relationshipSource);
    }
    $fieldSettings = $fieldDefinition->getItemDefinition()->getSettings();

    // Here we decide if our relationship is polymorphic or for a single entity/bundle type
    $relationshipEntityType = $fieldSettings['target_type'];
    $relationshipBundle = null;

    if(!empty($fieldSettings['handler_settings']['target_bundles']))
    {
      // with all entity references this shouldn't be a problem, however, in case of 'user', this is blank
      // luckally we handle this correctly in getModelClassForEntityAndBundle
      $relationshipBundle = reset($fieldSettings['handler_settings']['target_bundles']);
    }
    $this->firstModelType = Model::getModelClassForEntityAndBundle($relationshipEntityType, $relationshipBundle);

    if(isset($fieldSettings['handler_settings']['target_bundles']) && sizeof($fieldSettings['handler_settings']['target_bundles']) > 1)
    {
      $this->isPolymorphic = true;
      $this->modelType = null;

      foreach($fieldSettings['handler_settings']['target_bundles'] as $targetBundle)
      {
        $this->polymorphicModelTypes[] = Model::getModelClassForEntityAndBundle($relationshipEntityType, $targetBundle);
      }
    }
    else
    {
      $this->modelType = $this->firstModelType;
    }

    // Next we set the cardinality of the field, either we have a single reference or multiple references (single parent / multiple parents)
    $this->fieldCardinality = $fieldDefinition->getFieldStorageDefinition()->getCardinality();
  }

  /**
   * Returns the field part of the relationship (for example field_user.target_id will return "field_user")
   *
   * @return string
   */
  public function getField() : string
  {
    $positionOfDot = strpos($this->relationshipField, '.');
    return $positionOfDot ? substr($this->relationshipField, 0, $positionOfDot) : $this->relationshipField;
  }

  /**
   * Returns the column of the relationship (for example field_user.target_id will return "target_id")
   *
   * @return string
   */
  public function getColumn() : string
  {
    $positionOfDot = strpos($this->relationshipField, '.');
    return substr($this->relationshipField, $positionOfDot + 1); // exclude the "." so +1
  }

  /**
   * Magic getter for ease of access
   *
   * @param [type] $property
   * @return void
   */
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
