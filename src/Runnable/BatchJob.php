<?php

namespace Drupal\spectrum\Runnable;

use React\EventLoop\Factory;
use Drupal\spectrum\Model\Model;

abstract class BatchJob extends QueuedJob
{
  public final function execute() : void
  {
    $batchable = $this->getBatchable();
    $batchSize = $this->getBatchSize();
    $batchable->setBatchSize($batchSize);

    $sleep = $this->getSleep();
    $loop = Factory::create();
    $loop->addPeriodicTimer($sleep, function() use (&$loop, &$batchable, &$batchSize) {
      $batch = $batchable->getNextBatch();

      if(!empty($batch))
      {
        $this->processBatch($batch);

        Model::clearAllDrupalStaticEntityCaches();
      }

      if(empty($batch) || sizeof($batch) < $batchSize)
      {
        $loop->stop();
      }
    });

    $loop->run();
  }

  protected function getBatchSize() : int
  {
    $batchSize = $this->entity->field_batch_size->value;
    return empty($batchSize) ? 200 : $batchSize;
  }

  protected function getSleep() : float
  {
    $sleep = $this->entity->field_sleep->value;
    return empty($sleep) || ($sleep <= 0 ) ? 0.02 : $sleep;
  }

  protected abstract function processBatch(array $batch) : void;
  protected abstract function getBatchable() : BatchableInterface;
}
