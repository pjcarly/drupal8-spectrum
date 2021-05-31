<?php

namespace Drupal\spectrum\Command;

use Drupal\spectrum\Runnable\RegisteredJob;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Core\Cache\MemoryCache\MemoryCacheInterface;
use Drupal\Core\State\StateInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\spectrum\Services\ModelServiceInterface;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Query\Order;
use Drupal\spectrum\Runnable\QueuedJob;
use Drupal\spectrum\Services\ModelStoreInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;

/**
 * Class CronCommand.
 *
 * Drupal\Console\Annotations\DrupalCommand (
 *     extension="spectrum",
 *     extensionType="module"
 * )
 */
class CronCommand extends ContainerAwareCommand
{
  protected LoggerInterface $logger;
  protected LoopInterface $loop;
  protected MemoryCacheInterface $memoryCache;
  protected StateInterface $stateCache;
  protected ModelStoreInterface $modelStore;
  protected ModelServiceInterface $modelService;

  public function __construct(
    LoggerInterface $logger,
    LoopInterface $loop,
    MemoryCacheInterface $memoryCache,
    StateInterface $stateCache,
    ModelStoreInterface $modelStore,
    ModelServiceInterface $modelService
  ) {
    parent::__construct();
    $this->logger = $logger;
    $this->loop = $loop;
    $this->memoryCache = $memoryCache;
    $this->stateCache = $stateCache;
    $this->modelStore = $modelStore;
    $this->modelService = $modelService;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this
      ->setName('spectrum:cron:start')
      ->setAliases(['sp:cron'])
      ->setDescription('Starts the Cron Event Loop');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->getIo()->info('Spectrum Cron Started');

    $loop = $this->loop;

    $loop->addPeriodicTimer(1 / 4, function () use (&$loop, &$output) {
      $currentTime = new \DateTime('now', new \DateTimeZone('UTC'));

      $query = QueuedJob::getModelQuery();
      $query->addCondition(new Condition('field_job_status', 'IN', [QueuedJob::STATUS_QUEUED, QueuedJob::STATUS_RUNNING]));
      $query->addCondition(new Condition('field_scheduled_time', '<=', $currentTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT)));
      $query->addSortOrder(new Order('field_scheduled_time'));
      $query->setLimit(1);

      /** @var QueuedJob $earliestQueuedJob */
      $earliestQueuedJob = $query->fetchSingleModel();

      if (!empty($earliestQueuedJob)) {
        $this->clearAllCaches();
        $earliestQueuedJob->setOutput($output);
        /** @var RegisteredJob $job */
        $job = $earliestQueuedJob->fetch('job');

        if (!isset($job)) {
          $earliestQueuedJob->failedExecution(null, 'Registered Job no longer exists');
        } else {
          $class = $job->getJobClass();
          if (empty($class)) {
            $earliestQueuedJob->failedExecution(null, 'No class provided');
          } else if (!class_exists($class)) {
            $earliestQueuedJob->failedExecution(null, 'Class does not exist');
          } else if (!$job->isActive()) {
            $earliestQueuedJob->failedExecution(null, 'Registered job is inactive');
          } else {
            /** @var QueuedJob $job */
            $job = $class::forgeByEntity($earliestQueuedJob->entity);
            $job->setOutput($output);

            $job->run();
          }
        }

        $this->clearAllCaches();
      }
    });

    $loop->run();
  }

  /**
   * Clears all the caches
   *
   * @return self
   */
  protected function clearAllCaches(): self
  {
    // This will clear all the entity caches, and free entities from memory
    $this->modelService->clearDrupalEntityCachesForAllModels();

    // Reset some extra Drupal Caches
    $this->memoryCache->deleteAll();
    $this->stateCache->resetCache();

    // And finally clear the model store of any data as well
    $this->modelStore->unloadAll();
    return $this;
  }
}
