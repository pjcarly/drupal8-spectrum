<?php

namespace Drupal\spectrum\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Drupal\Console\Core\Command\ContainerAwareCommand;
use Drupal\Console\Annotations\DrupalCommand;
use Drupal\spectrum\Services\JobService;
use Symfony\Component\Console\Helper\Table;

/**
 * Class RegisteredJobsRebuildCommand.
 *
 * @DrupalCommand (
 *     extension="spectrum",
 *     extensionType="module"
 * )
 */
class RegisteredJobsRebuildCommand extends ContainerAwareCommand
{
  /** @var JobService */
  protected $jobService;

  public function __construct(JobService $jobService)
  {
    parent::__construct();
    $this->jobService = $jobService;
  }

  /**
   * {@inheritdoc}
   */
  protected function configure()
  {
    $this
      ->setName('spectrum:registered-jobs:rebuild')
      ->setAliases(['sp:rjr'])
      ->setDescription('Rebuilds the Registered Job database');
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output)
  {
    $this->jobService->rebuildRegisteredJobs();

    $newJobs = $this->jobService->getNewRegisteredJobs();
    $existingJobs = $this->jobService->getExistingFoundJobs();
    $removedJobs = $this->jobService->getRemovedJobs();

    sort($newJobs);
    sort($existingJobs);
    sort($removedJobs);

    $table = new Table($output);
    $table->setHeaders([
      'New (' . sizeof($newJobs) . ')',
      'Existing (' . sizeof($existingJobs) . ')',
      'Removed (' . sizeof($removedJobs) . ')'
    ]);

    foreach (max($newJobs, $existingJobs, $removedJobs) as $key => $j) {
      $table->addRow([
        array_key_exists($key, $newJobs) ? $newJobs[$key] : '',
        array_key_exists($key, $existingJobs) ? $existingJobs[$key] : '',
        array_key_exists($key, $removedJobs) ? $removedJobs[$key] : ''
      ]);
    }

    $table->render();
    $this->jobService->rebuildRegisteredJobs();
  }
}
