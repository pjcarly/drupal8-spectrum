<?php

namespace Drupal\spectrum\Query;

class Order
{
  public $fieldName;
  public $direction;
  public $langcode;

  public function __construct($fieldName, $direction = 'ASC', $langcode = null)
  {
    $this->fieldName = $fieldName;
    $this->direction = $direction;
    $this->langcode = $langcode;
  }
}
