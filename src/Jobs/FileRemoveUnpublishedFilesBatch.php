<?php

namespace Drupal\spectrum\Jobs;

use Drupal\Core\Entity\EntityInterface;
use Drupal\spectrum\Runnable\BatchJob;
use Drupal\spectrum\Runnable\BatchableInterface;
use Drupal\spectrum\Models\File;
use Drupal\spectrum\Query\Condition;

/**
 * This Batch job will remove unpublished files from the database and filesystem (through drupal triggers)
 */
class FileRemoveUnpublishedFilesBatch extends BatchJob
{
  /**
   * {@inheritdoc}
   */
  protected function getBatchable(): BatchableInterface
  {
    $query = File::getModelQuery();
    $query->addCondition(new Condition('status', '=', '0'));

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(EntityInterface $entity): void
  {
    $file = File::forgeByEntity($entity);

    $file->delete();
  }
}
