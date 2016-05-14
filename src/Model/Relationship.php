<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Exceptions\InvalidEntityException;

abstract class Relationship
{
	public $relationshipName;
  protected $relationshipSource; // the model where this relationship is defined

	public function __construct($relationshipName)
	{
		$this->relationshipName = $relationshipName;
	}

  public function setRelationshipSource($source = null)
  {
    if(empty($source))
    {
      throw new InvalidEntityException();
    }

    $this->relationshipSource = $source;
    $this->setModelType();
  }

  public function getRelationshipKey()
  {
    return $this->relationshipSource . '.' . $this->relationshipName;
  }

  protected abstract function setModelType();
  public abstract function getRelationshipQuery();
	public abstract function getCondition();
}
