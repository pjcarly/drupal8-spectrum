<?php
abstract class SpectrumOrder 
{
	public $fieldName;
	public $direction;

	public static $directions = array('ASC', 'DESC');

	public function __construct($fieldName, $direction = 'ASC')
	{
		$this->fieldName = $fieldName;
		$this->direction = $direction;

		if(!in_array($this->direction, SpectrumOrder::$directions))
		{
			throw new SpectrumInvalidDirectionException();
		}
	}

	public abstract function addQueryOrder($query);
}