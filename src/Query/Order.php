<?php

namespace Drupal\spectrum\Query;

/**
 * This class is used to add Sort Ordering to your query.
 */
class Order
{
  /**
   * The Drupal field name
   *
   * @var string
   */
  public $fieldName;

  /**
   * The sorting direction
   *
   * @var string
   */
  public $direction;

  /**
   * The language code
   *
   * @var string
   */
  public $langcode;

  /**
   * @param string $fieldName The Drupal field name
   * @param string $direction The sorting direction
   * @param string $langcode The language code
   */
  public function __construct(string $fieldName, string $direction = 'ASC', string $langcode = null)
  {
    $this->fieldName = $fieldName;
    $this->direction = $direction;
    $this->langcode = $langcode;
  }

  /**
   * Get the Drupal field name
   *
   * @return  string
   */
  public function getFieldName(): string
  {
    return $this->fieldName;
  }

  /**
   * Get the sorting direction
   *
   * @return  string
   */
  public function getDirection(): string
  {
    return $this->direction;
  }

  /**
   * Get the language code
   *
   * @return  string
   */
  public function getLangcode(): ?string
  {
    return $this->langcode;
  }
}
