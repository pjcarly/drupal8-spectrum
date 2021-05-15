<?php

namespace Drupal\spectrum\Services;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Runnable\Annotation\Job;
use Drupal\spectrum\Runnable\JobInterface;
use Drupal\spectrum\Runnable\RegisteredJob;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * The Job service is responsible for scheduling, running and registering them in the system.
 */
class JobService extends DefaultPluginManager implements LoggerAwareInterface
{
  protected LoggerInterface $logger;

  /** @var string[] */
  protected array $existingFoundJobs = [];

  /** @var string[] */
  protected array $newRegisteredJobs = [];

  /** @var string[] */
  protected array $removedJobs = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler,
    LoggerInterface $logger
  ) {
    parent::__construct('Jobs', $namespaces, $moduleHandler, JobInterface::class, Job::class);

    $this->setCacheBackend($cacheBackend, 'queud_job_plugins');
    $this->setLogger($logger);
  }

  /**
   * @inheritDoc
   */
  public function setLogger(LoggerInterface $logger)
  {
    $this->logger = $logger;
  }

  /**
   * Rebuilds all Registered Jobs based on the Jobs that are available in the Code
   *
   * @return self
   */
  public function rebuildRegisteredJobs(): self
  {
    $definitions = $this->getDefinitions();

    $query = RegisteredJob::getModelQuery();
    /** @var Collection|RegisteredJob[] */
    $currentJobs = $query->fetchCollection();
    $currentJobsByKey = $currentJobs->buildArrayByFieldName('title');

    foreach ($definitions as $key => $definition) {
      if (array_key_exists($key, $currentJobsByKey)) {
        $currentJobsByKey[$key]->selected = true;
        $currentJobsByKey[$key]->setJobClass($definition['class']);
      } else {
        /** @var RegisteredJob $newJob */
        $newJob = $currentJobs->putNew();
        $newJob->setTitle($definition['id']);
        $newJob->setJobClass($definition['class']);
        $newJob->setActive(TRUE);
        $newJob->selected = true;
      }
    }

    foreach ($currentJobs as $job) {
      if ($job->isNew()) {
        $this->newRegisteredJobs[] = $job->getTitle();
      } else if ($job->selected) {
        $this->existingFoundJobs[] = $job->getTitle();
      } else {
        $this->removedJobs[] = $job->getTitle();
      }
    }

    $currentJobs->removeNonSelectedModels();
    $currentJobs->save();

    return $this;
  }

  /**
   * @return integer
   */
  public function getAmountOfExistingFoundJobs(): int
  {
    return sizeof($this->existingFoundJobs);
  }

  /**
   * @return integer
   */
  public function getAmountOfNewRegisteredJobs(): int
  {
    return sizeof($this->newRegisteredJobs);
  }

  /**
   * @return integer
   */
  public function getAmountOfRemovedJobs(): int
  {
    return sizeof($this->removedJobs);
  }

  /**
   * @return string[]
   */
  public function getExistingFoundJobs(): array
  {
    return $this->existingFoundJobs;
  }

  /**
   * @return string[]
   */
  public function getNewRegisteredJobs(): array
  {
    return $this->newRegisteredJobs;
  }

  /**
   * @return string[]
   */
  public function getRemovedJobs(): array
  {
    return $this->removedJobs;
  }
}
