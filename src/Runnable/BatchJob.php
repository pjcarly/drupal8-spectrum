<?php

namespace Drupal\spectrum\Runnable;

use DateTime;
use DateTimeZone;
use Drupal;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\spectrum\Model\Model;
use Exception;
use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use React\Socket\ConnectorInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use function AdiMihaila\Promise\wait;
use function Clue\React\Block\await;

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
    $totalCounter = 0;
//    $this->sendStatus($totalCounter,$totalRecords);

    foreach ($batchable->getBatchGenerator() as $entity) {
      $this->process($entity);
      $progressBar->advance();
      $counter++;
      $totalCounter++;
      if ($counter % $batchSize === 0) {
        $this->clearCache();
//        $this->sendStatus($totalCounter,$totalRecords);
        $counter = 0;
      }
    }

//    $this->sendStatus($totalCounter,$totalCounter);
    $progressBar->finish();
    $this->getOutput()->writeln('');

    $this->afterExecute();
  }
  /*
    private function sendStatus(int $counter,int $total)
    {
      $loop = Factory::create();
      $connector = new Connector($loop);
      $socket = null;
      $connector('ws://websocket:8080',[],['Host' => 'localhost'])->then(function (WebSocket $conn) use (&$socket, $counter, $total){
        $object = new \stdClass();
        $object->data = new \stdClass();
        $object->data->id = $this->getId();
        $object->data->attributes = new \stdClass();
        $object->data->attributes->max = $total;
        $object->data->attributes->current = $counter;
        $object->meta = new \stdClass();
        $object->meta->type = 'update_batchjob_status';
        $conn->send(json_encode($object));
        $conn->close();
      });
      $loop->run();
    }
  */

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
