<?php
namespace Drupal\spectrum\Triggers;

use Drupal\spectrum\Model\Model;

class ModelTrigger
{
  public static function handle($entity, $trigger)
  {
    if(!empty($entity) && !empty($trigger))
    {
      $entityType = $entity->getEntityTypeId();
      $bundle = $entity->bundle();

      if(Model::hasModelClassForEntityAndBundle($entityType, $bundle))
      {
        $modelClass = Model::getModelClassForEntityAndBundle($entityType, $bundle);
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
            $model->afterDelete();
            $model->doCascadingDeletes();
          break;
        }
      }
    }
  }
}
