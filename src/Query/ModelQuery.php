<?php

namespace Drupal\spectrum\Query;

use Drupal\spectrum\Model\Collection;

class ModelQuery extends Query
{
	public $modelType;

	public function __construct($modelType)
	{
		parent::__construct($modelType::$entityType, $modelType::$bundle);
		$this->modelType = $modelType;
	}

	public function fetchCollection()
	{
		$entities = $this->fetch();
		return Collection::forge($this->modelType, null, $entities);
	}

	public function fetchSingleModel()
	{
		$entity = $this->fetchSingle();

    if($entity != null)
    {
      $modelType = $this->modelType;
  		return $modelType::forge($entity);
    }
    else
    {
      return null;
    }
	}
}
