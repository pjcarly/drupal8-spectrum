<?php

namespace Drupal\spectrum\Query;

class Order
{
	public $field;
	public $direction;
  public $langcode;

	public function __construct($field, $direction = 'ASC', $langcode = null)
	{
		$this->field = $field;
		$this->direction = $direction;
    $this->langcode = $langcode;
	}
}
