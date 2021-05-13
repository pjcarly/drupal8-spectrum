<?php

namespace Drupal\spectrum\Triggers;

use Drupal\spectrum\Model\Model;
use Drupal\Core\Entity\EntityInterface;
use Drupal\spectrum\Model\ModelServiceInterface;
use Drupal\spectrum\Runnable\QueuedJob;

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
   *
   * @return void
   * @throws \Drupal\spectrum\Exceptions\ModelClassNotDefinedException
   * @throws \Throwable
   */
  public static function handle(EntityInterface $entity, string $trigger): void
  {
    if (!\Drupal::hasService('spectrum.model')) {
      // Model Service not yet initialized, we skip the triggers, no need to check for models
      return;
    }

    /** @var ModelServiceInterface $modelService */
    $modelService = \Drupal::service("spectrum.model");

    $entityType = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    if ($modelService->hasModelClassForEntityAndBundle($entityType, $bundle)) {
      $modelClass = $modelService->getModelClassForEntityAndBundle($entityType, $bundle);

      // We check the model reference on the entity itself, this way we can reuse the previous model state in the triggers
      $modelOnEntity = $entity->__spectrumModel;
      if ($modelOnEntity && $modelOnEntity::entityType() === $entityType && $modelOnEntity::bundle() === $bundle) {
        $model = $modelOnEntity;
      } else {
        $model = $modelClass::forgeByEntity($entity);
      }

      if ($model instanceof QueuedJob) {
        // Lets look for the implementation.
        $modelClass = $model->entity->{'field_job'}->entity->{'field_class'}->value;

        /** @var Model $modelClass */
        $model = $modelClass::forgeByEntity($model->getEntity());
      }

      $connection = \Drupal::database();
      $transaction = $connection->startTransaction();
      try {
        /** @var Model $model */
        switch ($trigger) {
          case 'presave':
            if ($model->entity->isNew()) {
              $model->__setIsNewlyInserted(TRUE);
              $model->__setIsBeingDeleted(FALSE);
              $model->beforeInsert();
            } else {
              $model->__setIsNewlyInserted(FALSE);
              $model->__setIsBeingDeleted(FALSE);
              $model->beforeUpdate();
            }
            break;
          case 'insert':
            $model->__setIsNewlyInserted(TRUE);
            $model->__setIsBeingDeleted(FALSE);
            $model->setAccessPolicy();
            $model->afterInsert();
            break;
          case 'update':
            $model->__setIsNewlyInserted(FALSE);
            $model->__setIsBeingDeleted(FALSE);
            $model->setAccessPolicy();
            $model->afterUpdate();
            break;
          case 'predelete':
            $model->__setIsNewlyInserted(FALSE);
            $model->__setIsBeingDeleted(TRUE);

            try {
              $model->beforeDelete();
            } catch (\Exception $ex) {
              if (!getenv('IGNORE_DELETE_SAFETY')) {
                throw $ex;
              }
            }

            $model->unsetAccessPolicy();
            break;
          case 'delete':
            $model->__setIsNewlyInserted(FALSE);
            $model->__setIsBeingDeleted(TRUE);

            try {
              $model->afterDelete();
            } catch (\Exception $ex) {
              if (!getenv('IGNORE_DELETE_SAFETY')) {
                throw $ex;
              }
            }

            $model->doCascadingDeletes();
            break;
        }
      } catch (\Throwable $t) {
        $transaction->rollBack();
        throw $t;
      }
    }
  }
}
