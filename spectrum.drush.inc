<?php
use Drupal\spectrum\Runnable\QueuedJob;
use Drupal\spectrum\Runnable\RegisteredJob;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Query\Order;
use Drupal\spectrum\Model\Model;
use React\EventLoop\Factory;


/**
 * Implements hook_drush_command().
 */
function spectrum_drush_command()
{
  $commands['spectrum-scheduler'] = [
    'description' => 'Trigger the Spectrum scheduler (this should be done on system cron every X minutes)',
    'aliases' => ['sp-cron']
  ];
  $commands['spectrum-scheduler-react'] = [
    'description' => 'Run the Spectrum scheduler via ReactPHP (this should only be run once, but triggered every time the server restarts)',
    'aliases' => ['sp-cronr']
  ];
  $commands['spectrum-job'] = [
    'description' => 'Execute a single Queued Job',
    'aliases' => ['sp-job'],
    'arguments' => [
      'class' => 'The full name of the class to execute (eg \\Drupal\\spectrum\\Jobs\\MyCustomJob), make sure to escape backslashes with another backslash'
    ]
  ];
  $commands['spectrum-view-sql'] = [
    'description' => 'Generate views for easy querying',
    'aliases' => ['sp-vsql']
  ];

  return $commands;
}

function drush_spectrum_view_sql($entityType = null)
{
  if(empty($entityType))
  {
    drush_print('you must provide an entity type as an argument to this query');
  }

  $modelClasses = Model::getModelClasses();
  foreach($modelClasses as $modelClass)
  {
    if($modelClass::$entityType === $entityType)
    {
      $query = $modelClass::getViewSelectQuery();

      if(!empty($query))
      {
        $viewName = empty($modelClass::$bundle) ? $modelClass::$entityType : $modelClass::$bundle;
        db_query('DROP VIEW IF EXISTS `view_'.$viewName.'`');

        $query = 'CREATE VIEW `view_'.$viewName.'` AS '.$query;
        db_query($query);
      }
    }
  }
}

function drush_spectrum_job($jobName = null)
{
  if(empty($jobName))
  {
    drush_print('No class provided, pass an registered job by name (eg "drush sp-job MyCustomJob")');
  }

  $jobQuery = RegisteredJob::getModelQuery();
  $jobQuery->addCondition(new Condition('title', '=', $jobName));
  $job = $jobQuery->fetchSingleModel();

  if(empty($job))
  {
    drush_print('Job not found');
  }
  else if(!$job->entity->field_active->value)
  {
    drush_print('Job is not active, activate it before you can run it');
  }
  else
  {
    $job->singleRun();
  }
}

function drush_spectrum_scheduler_react()
{
  $loop = Factory::create();

  $loop->addPeriodicTimer(1/4, function(){

    $currentTime = gmdate("Y-m-d\TH:i:s");
    $query = QueuedJob::getModelQuery();
    $query->addCondition(new Condition('field_job_status', 'IN', ['Queued', 'Running']));
    $query->addCondition(new Condition('field_scheduled_time', '<=', $currentTime));
    $query->addSortOrder(new Order('field_scheduled_time'));
    $query->setLimit(1);

    $earliestQueuedJob = $query->fetchSingleModel();
    if(!empty($earliestQueuedJob))
    {
      $job = $earliestQueuedJob->fetch('job');
      $class = $job->entity->field_class->value;
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

    // $activeSecond = gmdate('s');
    // $GLOBALS['count']++;

    // if($GLOBALS['lastSecond'] !== $activeSecond)
    // {
    //   $GLOBALS['lastSecond'] = $activeSecond;
    //   drush_print('Loops per Second: '.$GLOBALS['count']);
    //   $GLOBALS['count'] = 0;
    // }
  });

  $loop->run();
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
      $job = $earliestQueuedJob->fetch('job');
      $class = $job->entity->field_class->value;
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
