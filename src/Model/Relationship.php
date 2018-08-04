<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Exceptions\InvalidEntityException;

abstract class Relationship
{
  public static $NO_CASCADE = 0;
  public static $CASCADE_ON_DELETE = 1;

  public $relationshipName;
  protected $relationshipSource; // the model where this relationship is defined

  public $cascadingDelete = false;

  public function __construct($relationshipName, $cascade = 0)
  {
    $this->relationshipName = $relationshipName;
    $this->setCascadingDelete($cascade === 1);
  }

  public function setCascadingDelete($cascadingDelete) : Relationship
  {
    $this->cascadingDelete = $cascadingDelete;
    return $this; // to enable
  }

  public function setRelationshipSource($source = null) : Relationship
  {
    if(empty($source))
    {
      throw new InvalidEntityException();
    }

    $this->relationshipSource = $source;
    $this->setRelationshipMetaData();

    return $this;
  }

  public function getSourceModelType()
  {
    return $this->relationshipSource;
  }

  public function getRelationshipKey()
  {
    return $this->relationshipName;
  }

  protected function setRelationshipMetaData(){}
  public abstract function getRelationshipQuery();
  public abstract function getCondition();
}
