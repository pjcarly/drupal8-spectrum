<?php
use Drupal\spectrum\Runnable\QueuedJob;
use Drupal\spectrum\Query\Condition;

/**
 * Implements hook_drush_command().
 */
function spectrum_drush_command()
{
  $commands['spectrum-scheduler'] = [
    'description' => 'Trigger the Spectrum scheduler.',
    'aliases' => ['sp-cron']
  ];
  $commands['spectrum-job'] = [
    'description' => 'Execute a single Queued Job',
    'aliases' => ['sp-job'],
    'arguments' => [
      'class' => 'The full name of the class to execute (eg \\Drupal\\spectrum\\Jobs\\MyCustomJob), make sure to escape backslashes with another backslash'
    ]
  ];

  return $commands;
}

function drush_spectrum_job($class)
{
  $class = str_replace('\\\\', '\\', $class);

  if(empty($class))
  {
    drush_print('No class provided, pass an argument (eg "drush sp-job \Drupal\spectrum\Jobs\MyCustomJob")');
  }
  else if(!class_exists($class))
  {
    drush_print('Class does not exist');
  }
  else
  {
    $job = $class::createNew();
    $job->singleRun();
  }
}

function drush_spectrum_scheduler()
{
  $currentTime = gmdate("Y-m-d\TH:i:s");

  $query = QueuedJob::getModelQuery();
  $query->addCondition(new Condition('field_job_status', 'IN', ['Queued', 'Running']));
  $query->addCondition(new Condition('field_scheduled_time', '<=', $currentTime));

  $queuedJobs = $query->fetchCollection();

  if(!empty($queuedJobs) && !$queuedJobs->isEmpty)
  {
    $runningJob = null;
    $earliestQueuedJob = null;

    foreach($queuedJobs as $queuedJob)
    {
      if($queuedJob->entity->field_job_status->value === 'Running')
      {
        drush_print('Job '.$queuedJob->getId().' running');
        $runningJob = $queuedJob;
      }
      else if($queuedJob->entity->field_job_status->value === 'Queued')
      {
        drush_print('Job '.$queuedJob->getId().' queued');

        if(empty($earliestQueuedJob) || $queuedJob->entity->field_scheduled_time->value < $earliestQueuedJob->entity->field_scheduled_time->value)
        {
          $earliestQueuedJob = $queuedJob;
        }
      }
    }

    $shouldStartNextJob = false;
    if(empty($runningJob))
    {
      $shouldStartNextJob = true;
    }
    else
    {
      $scheduledTime = new \DateTime($runningJob->entity->field_scheduled_time->value);
      $currentTime = new \DateTime();

      $difference = $scheduledTime->diff($currentTime);
      $minutesToFailure = empty($runningJob->entity->field_minutes_to_failure->value) ? 20 : $runningJob->entity->field_minutes_to_failure->value;

      if($minutesToFailure + 11 <= $difference->i) // We add 11 minutes buffer (11 as the default cron time is 10 minutes, and we add 1)
      {
        drush_print('Time Exceeded by '.$difference->i.' minutes on Job '.$runningJob->getId());
        $runningJob->failedExecution(null, 'Time Exceeded');
        $shouldStartNextJob = true;
      }
    }

    if($shouldStartNextJob && !empty($earliestQueuedJob))
    {
      $class = $earliestQueuedJob->entity->field_class->value;
      if(empty($class))
      {
        $earliestQueuedJob->failedExecution(null, 'No class provided');
      }
      else if(!class_exists($class))
      {
        $earliestQueuedJob->failedExecution(null, 'Class does not exist');
      }
      else
      {
        $job = $class::forgeByEntity($earliestQueuedJob->entity);
        $job->run();
      }
    }
  }
  else
  {
    drush_print('No queued jobs ready for execution at: '.$currentTime);
  }
}
