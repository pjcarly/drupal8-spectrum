<?php

namespace Drupal\spectrum\Runnable;

use React\EventLoop\Factory;
use Drupal\spectrum\Model\Model;

abstract class BatchJob extends QueuedJob
{
  /**
   * {@inheritdoc}
   */
  public static function scheduleBatch(string $jobName, string $variable = '', \DateTime $date = null, int $batchSize = null): BatchJob
  {
    $registeredJob = RegisteredJob::getByKey($jobName);

    if (empty($registeredJob)) {
      throw new \Exception('Regisered Job (' . $jobName . ') not found');
    }

    if (empty($date)) {
      $utc = new \DateTimeZone('UTC');
      $date = new \DateTime();
      $date->setTimezone($utc);
    }

    $queuedJob = $registeredJob->createJobInstance();
    $queuedJob->entity->title->value = $jobName;

    if (!empty($variable)) {
      $queuedJob->entity->field_variable->value = $variable;
    }

    $queuedJob->entity->field_batch_size->value = $batchSize;
    $queuedJob->entity->field_minutes_to_failure->value = 10;
    $queuedJob->entity->field_scheduled_time->value = $date->format('Y-m-d\TH:i:s');
    $queuedJob->put('job', $registeredJob);
    $queuedJob->save();

    return $queuedJob;
  }

  public final function execute(): void
  {
    $batchable = $this->getBatchable();
    $batchSize = $this->getBatchSize();
    $batchable->setBatchSize($batchSize);

    $totalRecords = $batchable->getTotalBatchedRecords();

    $sleep = $this->getSleep();
    $loop = Factory::create();

    $loopCycle = 0;
    $loop->addPeriodicTimer($sleep, function () use (&$loop, &$batchable, &$batchSize, &$totalRecords, &$loopCycle) {
      $batch = $batchable->getNextBatch();
      $loopCycle++;

      if (!empty($batch)) {
        $recordsProcessed = (($loopCycle - 1) * $batchSize) + sizeof($batch);
        $memory = memory_get_usage() / 1024;
        $memoryUsage = ($memory < 1024) ? number_format($memory, 2, ',', ' ') . ' KB' : number_format($memory / 1024, 2, ',', ' ') . ' MB';

        if (!empty($totalRecords)) {
          $this->print(sprintf('(%s) Processing %u/%u (%s)', $this->entity->title->value, $recordsProcessed, $totalRecords, $memoryUsage));
        } else {
          $this->print(sprintf('(%s) Processing %u (%s)', $this->entity->title->value, $recordsProcessed, $memoryUsage));
        }

        $this->processBatch($batch);

        Model::clearAllDrupalStaticEntityCaches();
      }

      if (empty($batch) || sizeof($batch) < $batchSize) {
        $loop->stop();
      }
    });

    $loop->run();
  }

  protected function getBatchSize(): int
  {
    $batchSize = $this->entity->field_batch_size->value;
    return empty($batchSize) ? 200 : $batchSize;
  }

  protected function getSleep(): float
  {
    $sleep = $this->entity->field_sleep->value;
    return empty($sleep) || ($sleep <= 0) ? 0.02 : $sleep;
  }

  protected abstract function processBatch(array $batch): void;
  protected abstract function getBatchable(): BatchableInterface;
}
