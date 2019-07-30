<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\EntityQuery;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Exceptions\InvalidEntityException;

/**
 * All relationships should extend this abstract class
 */
abstract class Relationship
{
  /**
   * Mark a relationship as NO CASCADE (default)
   *
   * @var integer
   */
  public static $NO_CASCADE = 0;

  /**
   * Mark a relationship as CASCADE ON DELETE
   *
   * @var integer
   */
  public static $CASCADE_ON_DELETE = 1;

  /**
   * The name you wish to give your relationship
   *
   * @var string
   */
  public $name;

  /**
   * The fully qualified classname of the model where this relationship is defined
   *
   * @var string
   */
  protected $relationshipSource;

  /**
   * Whether this relationship will be cascading deleted (default = false)
   *
   * @var boolean
   */
  public $cascadingDelete = false;

  /**
   * The fully qualified classname of the model you wish to relate. This might not be the FQC passed in the constructor, a lookup will be done to see if another registered (overridden) modelclass exists
   *
   * @var string
   */
  public $modelType;

  /**
   * @param string $relationshipName the name you want to give to your relationship
   * @param integer $cascade the Cascade indicator (NO_CASCADE, and CASCADE_ON_DELETE are available as static ints on Relationship)
   */
  public function __construct(string $name, int $cascade = 0)
  {
    $this->name = $name;
    $this->setCascadingDelete($cascade === 1);
  }

  /**
   * Set the cascading delete flag
   *
   * @param boolean $cascadingDelete
   * @return Relationship
   */
  public function setCascadingDelete(bool $cascadingDelete): Relationship
  {
    $this->cascadingDelete = $cascadingDelete;
    return $this;
  }

  /**
   * Sets the source where the relationship is defined
   *
   * @param string $source
   * @return Relationship
   */
  public function setRelationshipSource(string $source): Relationship
  {
    $this->relationshipSource = $source;
    $this->setRelationshipMetaData();

    return $this;
  }

  /**
   * Returns the fully qualified classname of the source where this relationship was defined
   *
   * @return string
   */
  public function getSourceModelType(): string
  {
    return $this->relationshipSource;
  }

  /**
   * Returns the name of this relationship
   *
   * @return string
   */
  public function getName(): string
  {
    return $this->name;
  }

  /**
   * Returns the modelType
   *
   * @return string
   */
  public function getModelType(): string
  {
    return $this->modelType;
  }

  /**
   * This function will be called to set the metadata of the relationship
   *
   * @return void
   */
  protected function setRelationshipMetaData(): void
  { }

  /**
   * Returns a EntityQuery that can be used to query entities of this relationship
   *
   * @return EntityQuery
   */
  public abstract function getRelationshipQuery(): EntityQuery;

  /**
   * Returns a Condition, with the correct relationship field and operator filled in. The value will be blank and dependend on implementation
   *
   * @return Condition
   */
  public abstract function getCondition(): Condition;
}
