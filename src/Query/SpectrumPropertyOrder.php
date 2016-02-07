<?php
class SpectrumPropertyOrder extends SpectrumOrder
{
	public function addQueryOrder($query) 
	{
		$query->propertyOrderBy($this->fieldName, $this->direction);
	}
}