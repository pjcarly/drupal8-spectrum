<?php

namespace Drupal\spectrum\Query;

/**
 * This class is used to add Aggregations to an Aggregate Query
 */
class Aggregation
{
  /**
   * The Drupal field name
   *
   * @var string
   */
  private $fieldName;

  /**
   * The sorting aggregateFunction
   *
   * @var string
   */
  private $aggregateFunction;

  /**
   * @param string $fieldName The Drupal field name
   * @param string $aggregateFunction The sorting aggregateFunction
   */
  public function __construct(string $fieldName, string $aggregateFunction)
  {
    $this->fieldName = $fieldName;
    $this->aggregateFunction = $aggregateFunction;
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
   * @param string $fieldName
   * @return self
   */
  public function setFieldName(string $fieldName): self
  {
    $this->fieldName = $fieldName;
    return $this;
  }

  /**
   * Get the sorting aggregateFunction
   *
   * @return  string
   */
  public function getAggregateFunction(): string
  {
    return $this->aggregateFunction;
  }

  /**
   * @param string $aggregateFunction
   * @return self
   */
  public function setAggregateFunction(string $aggregateFunction): self
  {
    $this->aggregateFunction = $aggregateFunction;
    return $this;
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

  /**
   * @param string|null $langcode
   * @return void
   */
  public function setLangCode(?string $langcode)
  {
    $this->langcode = $langcode;
    return $this;
  }
}
