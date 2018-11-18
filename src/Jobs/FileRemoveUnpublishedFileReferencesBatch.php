<?php
namespace Drupal\spectrum\Jobs;

use Drupal\spectrum\Runnable\BatchJob;
use Drupal\spectrum\Runnable\BatchableInterface;
use Drupal\spectrum\Models\File;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Model\Collection;

/**
 * This Batch job will remove all the references to unpublished files, and put the target_id to false
 */
class FileRemoveUnpublishedFileReferencesBatch extends BatchJob
{
  /**
   * {@inheritdoc}
   */
  protected function getBatchable() : BatchableInterface
  {
    $query = File::getModelQuery();
    $query->addCondition(new Condition('status', '=', '0'));
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function processBatch(array $batch) : void
  {
    $files = Collection::forgeByEntities(File::class, $batch);
    foreach($files as $file)
    {
      $file->removeFromReferences();
    }
  }
}
