<?php

namespace Drupal\spectrum\Query;

/**
 * This class is used to add an Expression to your query.
 */
class Expression
{
  /**
   * The Drupal field name
   *
   * @var string
   */
  protected $name;

  /**
   * The expression
   *
   * @var string
   */
  protected $expression;

  /**
   * The fields being used in the expression
   *
   * @var string[]
   */
  protected $fields;

  /**
   * @param string $name The name of your expression
   * @param string $expression The sorting expression
   * @param string[] $fields In order to correctly parse the expression, we need the unique fields that are being used in the expression
   */
  public function __construct(string $name, string $expression, array $fields)
  {
    $this->name = $name;
    $this->expression = $expression;
    $this->fields = $fields;
  }

  /**
   * Get the Drupal field name
   *
   * @return  string
   */
  public function getName() : string
  {
    return $this->name;
  }

  /**
   * Get the sorting expression
   *
   * @return  string
   */
  public function getExpression() : string
  {
    return $this->expression;
  }

  /**
   * Gets the fields being used in the expression
   *
   * @return string[]
   */
  public function getFields() : array
  {
    return $this->fields;
  }
}
