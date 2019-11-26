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

    $entityType = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    if (Model::hasModelClassForEntityAndBundle($entityType, $bundle)) {
      $modelClass = Model::getModelClassForEntityAndBundle($entityType,
        $bundle);

      // We check the model reference on the entity itself, this way we can reuse the previous model state in the triggers
      $modelOnEntity = $entity->__spectrumModel;
      if ($modelOnEntity && $modelOnEntity::entityType() === $entityType && $modelOnEntity::bundle() === $bundle) {
        $model = $modelOnEntity;
      } else {
        $model = $modelClass::forgeByEntity($entity);
      }
      $connection = \Drupal::database();
      $transaction = $connection->startTransaction();
      try {
        /** @var Model $model */
        switch ($trigger) {
          case 'presave':
            if ($model->entity->isNew()) {
              $model->__setIsNewlyInserted(true);
              $model->beforeInsert();
            } else {
              $model->__setIsNewlyInserted(false);
              $model->beforeUpdate();
            }
            break;
          case 'insert':
            $model->__setIsNewlyInserted(true);
            $model->setAccessPolicy();
            $model->afterInsert();
            break;
          case 'update':
            $model->__setIsNewlyInserted(false);
            $model->setAccessPolicy();
            $model->afterUpdate();
            break;
          case 'predelete':
            $model->__setIsNewlyInserted(false);
            $model->beforeDelete();
            $model->unsetAccessPolicy();
            break;
          case 'delete':
            $model->__setIsNewlyInserted(false);
            $model->afterDelete();
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
