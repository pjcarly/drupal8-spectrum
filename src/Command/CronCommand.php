<?php

namespace Drupal\spectrum\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Annotations\DrupalCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Drupal\spectrum\Query\EntityQuery;
use Drupal\Core\Entity\EntityInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Query\Order;
use Drupal\spectrum\Runnable\BatchJob;
use Drupal\spectrum\Runnable\QueuedJob;
use React\EventLoop\Factory;

/**
 * Class ActivitiesDeleteCommand.
 *
 * @DrupalCommand (
 *     extension="spectrum",
 *     extensionType="module"
 * )
 */
class CronCommand extends ContainerAwareCommand
{

  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this
      ->setName('spectrum:cron:start')
      ->setDescription('Starts the Cron Event Loop');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $loop = Factory::create();

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
        $earliestQueuedJob->setOutput($output);
        /** @var RegisteredJob $job */
        $job = $earliestQueuedJob->fetch('job');

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

        // This will clear all the entity caches, and free entities from memory
        Model::clearAllDrupalStaticEntityCaches();

        /** @var \Drupal\Core\Cache\MemoryCache\MemoryCacheInterface $cache */
        $cache = \Drupal::service('entity.memory_cache');
        $cache->deleteAll();
      }
    });

    $loop->run();
  }
}