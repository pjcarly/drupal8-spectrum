<?php
namespace Drupal\spectrum\Jobs;

use Drupal\spectrum\Runnable\BatchJob;
use Drupal\spectrum\Runnable\BatchableInterface;
use Drupal\spectrum\Models\File;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Model\Collection;

class FileRemoveUnpublishedFileReferencesBatch extends BatchJob
{
  protected function getBatchable() : BatchableInterface
  {
    $query = File::getModelQuery();
    $query->addCondition(new Condition('status', '=', '0'));
    return $query;
  }

  protected function processBatch(array $batch) : void
  {
    $files = Collection::forgeByEntities('Drupal\spectrum\Models\File', $batch);
    foreach($files as $file)
    {
      $file->removeFromReferences();
    }
  }
}
