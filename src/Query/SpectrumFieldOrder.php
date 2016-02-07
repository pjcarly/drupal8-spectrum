<?php
class SpectrumFieldOrder extends SpectrumOrder
{
	private $column;

	public function __construct($fieldName, $column, $direction = 'ASC')
	{
		parent::__construct($fieldName, $direction);
		$this->column = $column;
	}

	public function addQueryOrder($query) 
	{
		$query->fieldOrderBy($this->fieldName, $this->column, $this->direction);
	}
}