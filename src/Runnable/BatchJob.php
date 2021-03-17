<?php

namespace Drupal\spectrum\Runnable;

use DateTime;
use DateTimeZone;
use Drupal;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\spectrum\Event\CronStatusUpdatedEvent;
use Drupal\spectrum\Model\Model;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\EventDispatcher\EventDispatcher;

abstract class BatchJob extends QueuedJob
{
  /**
   * {@inheritdoc}
   */
  public static function scheduleBatch(string $jobName, string $variable = null, DateTime $date = null, int $batchSize = null, string $relatedEntity = null, string $relatedBundle = null, string $relatedModelId = null): BatchJob
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
    $queuedJob->setRelatedEntity($relatedEntity);
    $queuedJob->setRelatedBundle($relatedBundle);
    $queuedJob->setRelatedModelId($relatedModelId);

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
    /** @var EventDispatcher $eventDispatcher */
    $eventDispatcher = Drupal::service('event_dispatcher');

    $progressBar = new ProgressBar($this->output, $totalRecords);
    $progressBar->setFormat('debug');
    $progressBar->start();
    $counter = 0;
    $totalCounter = 0;

    if ($this->updateCronStatus) {
      $event = new CronStatusUpdatedEvent($this, $totalCounter, $totalRecords, Drupal::service('react.loop'));
      $eventDispatcher->dispatch($event);
    }

    foreach ($batchable->getBatchGenerator() as $entity) {
      $this->process($entity);
      $progressBar->advance();
      $counter++;
      $totalCounter++;
      if ($counter % $batchSize === 0) {
        $this->clearCache();

        if ($this->updateCronStatus) {
          $event = new CronStatusUpdatedEvent($this, $totalCounter, $totalRecords, Drupal::service('react.loop'));
          $eventDispatcher->dispatch($event);
        }

        $counter = 0;
      }
    }

    if ($this->updateCronStatus) {
      $event = new CronStatusUpdatedEvent($this, $totalCounter, $totalRecords, Drupal::service('react.loop'));
      $eventDispatcher->dispatch($event);
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
