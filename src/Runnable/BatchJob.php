<?php

namespace Drupal\spectrum\Runnable;

use DateTime;
use DateTimeZone;
use Drupal;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\spectrum\Model\Model;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;

abstract class BatchJob extends QueuedJob
{
  /**
   * {@inheritdoc}
   */
  public static function scheduleBatch(string $jobName, string $variable = '', DateTime $date = null, int $batchSize = null): BatchJob
  {
    $registeredJob = RegisteredJob::getByKey($jobName);

    if (empty($registeredJob)) {
      throw new Exception('Registered Job (' . $jobName . ') not found');
    }

    if (empty($date)) {
      $date = new DateTime('now', new DateTimeZone('UTC'));
    }

    /** @var BatchJob $queuedJob */
    $queuedJob = $registeredJob->createJobInstance();
    $queuedJob->setTitle($jobName);

    if (!empty($variable)) {
      $queuedJob->setVariable($variable);
    }

    $queuedJob->setBatchSize($batchSize);
    $queuedJob->setMinutesToFailure(10);
    $queuedJob->setScheduledTime($date);


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

    $progressBar = new ProgressBar($this->output, $totalRecords);
    $progressBar->setFormat('debug');
    $progressBar->start();
    $counter = 0;
    foreach ($batchable->getBatchGenerator() as $entity) {
      $this->process($entity);
      $progressBar->advance();
      $counter++;
      if ($counter % $batchSize === 0) {
        $this->clearCache();
        $counter = 0;
      }
    }

    $progressBar->finish();
    $this->getOutput()->writeln('');

    $this->afterExecute();
  }

  /**
   * Hook that is called after the batch job is fully executed
   *
   * @return void
   */
  protected function afterExecute(): void
  {
  }

  /**
   * @return float
   */
  protected function getSleep(): float
  {
    $sleep = $this->entity->{'field_sleep'}->value;
    return empty($sleep) || ($sleep <= 0) ? 0 : $sleep;
  }

  /**
   * @return int
   */
  public function getBatchSize(): int
  {
    return $this->entity->{'field_batch_size'}->value ?? 200;
  }

  /**
   * @param int $value
   * @return self
   */
  public function setBatchSize(?int $value): QueuedJob
  {
    $this->entity->{'field_batch_size'}->value = $value;
    return $this;
  }

  protected abstract function process(EntityInterface $entity): void;
  protected abstract function getBatchable(): BatchableInterface;

  private function clearCache()
  {
    // This will clear all the entity caches, and free entities from memory
    Model::clearAllDrupalStaticEntityCaches();

    /** @var MemoryCacheInterface $cache */
    $cache = Drupal::service('entity.memory_cache');
    $cache->deleteAll();

    // And finally clear the model store of any data as well
    Model::getModelStore()->unloadAll();
  }
}
