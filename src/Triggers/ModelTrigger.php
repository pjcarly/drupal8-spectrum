<?php
namespace Drupal\spectrum\Triggers;

class ModelTrigger
{
  private $modelClasses;

  public function __construct($modelClasses)
  {
    $this->modelClasses = $modelClasses;
  }

  function handle($entity, $trigger)
  {
    if(!empty($entity) && !empty($trigger))
    {
      $modelClass = $this->getModelClassForEntity($entity);
      if(!empty($modelClass))
      {
        $model = new $modelClass($entity);
        switch($trigger)
        {
          case 'presave':
            if($model->entity->isNew())
            {
              $model->beforeInsert();
            }
            else
            {
              $model->beforeUpdate();
            }
          break;
          case 'insert':
            $model->afterInsert();
          break;
          case 'update':
            $model->afterUpdate();
          break;
          case 'delete':
            $model->beforeDelete();
          break;
        }
      }
    }
  }

  function getModelClassForEntity($entity)
  {
    $entityType = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $foundModelClass = null;

    foreach($this->modelClasses as $modelClass)
    {
      if($modelClass::$entityType === $entityType && $modelClass::$bundle == $bundle)
      {
        $foundModelClass = $modelClass;
        break;
      }
    }

    return $foundModelClass;
  }
}
