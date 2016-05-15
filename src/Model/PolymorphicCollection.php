<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Exceptions\PolymorphicException;

class PolymorphicCollection extends Collection
{
  private $entityType;

	public function save($relationshipName = NULL)
	{
		if(!empty($relationshipName))
		{
      throw new PolymorphicException('Relationship path "'.$relationshipName.'" has no meaning for polymorphic collections');
    }

		return parent::save();
	}

  public function validate($relationshipName = NULL)
  {
    if(!empty($relationshipName))
		{
      throw new PolymorphicException('Relationship path "'.$relationshipName.'" has no meaning for polymorphic collections');
    }

    return parent::validate();
  }

	public function fetch($relationshipName)
	{
    throw new PolymorphicException('Fetch has no meaning for polymorphic collections');
	}

	public function put($model)
	{
    // it is only possible to have models with a shared entity in a collection
    if(empty($this->entityType))
    {
      $this->entityType = $model::$entityType;
    }
    else if($this->entityType !== $model::$entityType)
    {
      throw new PolymorphicException('Only models with a shared entity type are allowed in a polymorphic collection')
    }

    // due to the the shared entity constraint, the key of polymorphic collections is unique,
    // because in drupal ids are unique over different bundles withing the same entity
		if(!array_key_exists($model->key, $this->models))
		{
			$this->models[$model->key] = $model;
			$this->originalModels[$model->key] = $model;
		}
	}

	public function get($relationshipName)
	{
		throw new PolymorphicException('Get has no meaning for polymorphic collections');
	}
}
