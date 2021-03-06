<?php

namespace Drupal\spectrum\Model;

use Drupal\spectrum\Query\EntityQuery;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Services\ModelServiceInterface;

/**
 * All relationships should extend this abstract class
 */
abstract class Relationship
{
  protected ModelServiceInterface $modelService;

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
   * @var integer
   */
  public static $CASCADE_NO_DELETE = 2;

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
   * Cascade type, 0 = no cascade; 1 = cascade on delete; 2 = cascade no delete
   *
   * @var boolean
   */
  public $cascadeType = 0;

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
    $this->setCascadeType($cascade);
    $this->modelService = \Drupal::service("spectrum.model");
  }

  public function setCascadeType(int $value): Relationship
  {
    $this->cascadeType = $value;
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
  {
  }

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

  /**
   * @return bool
   */
  public function isCascadeOnDelete(): bool
  {
    return $this->cascadeType === $this::$CASCADE_ON_DELETE;
  }

  /**
   * @return bool
   */
  public function isCascadeNoDelete(): bool
  {
    return $this->cascadeType === $this::$CASCADE_NO_DELETE;
  }

  /**
   * @return bool
   */
  public function isNoCascade(): bool
  {
    return $this->cascadeType === $this::$NO_CASCADE;
  }
}
