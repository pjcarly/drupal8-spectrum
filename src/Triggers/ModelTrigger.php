<?php
namespace Drupal\spectrum\Triggers;

use Drupal\spectrum\Model\Model;
use Drupal\Core\Entity\EntityInterface;

/**
 * This class provides functionality to translate drupal entity hooks, to model trigger methods
 */
class ModelTrigger
{
  /**
   * Handle the drupal entity hook, the passed in entity will be wrapped in a Model,
   * and the trigger function corresponding to the type of trigger on that model will be invoked
   *
   * @param EntityInterface $entity
   * @param string $trigger
   * @return void
   */
  public static function handle(EntityInterface $entity, string $trigger) : void
  {
    if(!\Drupal::hasService('spectrum.model'))
    {
      // Model Service not yet initialized, we skip the triggers, no need to check for models
      return;
    }

    $entityType = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    if(Model::hasModelClassForEntityAndBundle($entityType, $bundle))
    {
      $modelClass = Model::getModelClassForEntityAndBundle($entityType, $bundle);
      $model = $modelClass::forgeByEntity($entity);

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
        case 'predelete':
          $model->beforeDelete();
        break;
        case 'delete':
          $model->afterDelete();
          $model->doCascadingDeletes();
        break;
      }
    }
  }
}
