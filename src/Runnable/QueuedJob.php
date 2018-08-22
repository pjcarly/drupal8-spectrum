<?php
namespace Drupal\spectrum\Runnable;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\AccountSwitcher;
use Drupal\spectrum\Runnable\RegisteredJob;
use Drupal\spectrum\Exceptions\JobTerminateException;

/**
 * A queued job is an implementation of RunnableModel, it can be scheduled to be executed on a later time.
 * QueuedJob itself shouldnt be instantiated. It should be extended with functionality
 * We cannot mark the class abstract, as on Query time for new QueuedJobs we dont know the Fully Qualified Classname of the implementation
 */
class QueuedJob extends RunnableModel
{
  public static $entityType = 'runnable';
  public static $bundle = 'queued_job';

  /**
   * An instance of AccountSwitcher. This gives you the ability to execute the Job as another user, and switch back afterwards.
   *
   * @var Drupal\Core\Session\AccountSwitcherInterface
   */
  private $accountSwitcher;

  public static function relationships()
  {
    parent::relationships();
  }

  /**
   * This function will be executed just before starting the execution of the job.
   * Here the status will be set to Runninng and the start time will be set to the current time
   *
   * @return void
   */
  public final function preExecution() : void
  {
    $currentTime = gmdate('Y-m-d\TH:i:s');
    $this->print('Job with ID: '.$this->getId().' STARTED at '.$currentTime . ' ('.$this->entity->title->value.')');

    $this->entity->field_job_status->value = 'Running';
    $this->entity->field_start_time->value = $currentTime;
    $this->entity->field_error_message->value = null;
    $this->entity->field_end_time->value = null;
    $this->save();

    // Check the user context we need to execute in, and switch to the provided user if necessary.
    // If no provided user, execute as anonymous

    $this->accountSwitcher = \Drupal::service('account_switcher');
    if(empty($this->entity->field_run_as->target_id) || $this->entity->field_run_as->target_id === 0 || empty($this->entity->field_run_as->entity))
    {
      $this->accountSwitcher->switchTo(new AnonymousUserSession());
    }
    else
    {
      $this->accountSwitcher->switchTo($this->entity->field_run_as->entity);
    }
  }

  /**
   * Execute the job, this function should be overridden by every job, to provide an implementation
   *
   * @return void
   */
  public function execute() : void {}

  /**
   * Schedule a job on a given datetime, with a possible variable.
   *
   * @param string $jobName (required) The Name of the Job
   * @param string $variable (optional) Provide a variable for the job, it can be accessed on execution time
   * @param \DateTime $date (optional) The date you want to schedule the job on. If left blank, "now" will be chosen
   * @return QueuedJob
   */
  public static function schedule(string $jobName, string $variable = '', \DateTime $date = null) : QueuedJob
  {
    $registeredJob = RegisteredJob::getByKey($jobName);

    if(empty($registeredJob))
    {
      throw new \Exception('Regisered Job ('.$jobName.') not found');
    }

    if(empty($date))
    {
      $utc = new \DateTimeZone('UTC');
      $date = new \DateTime();
      $date->setTimezone($utc);
    }

    $queuedJob = $registeredJob->createJobInstance();
    $queuedJob->entity->title->value = $jobName;

    if(!empty($variable))
    {
      $queuedJob->entity->field_variable->value = $variable;
    }

    $queuedJob->entity->field_minutes_to_failure->value = 10;
    $queuedJob->entity->field_scheduled_time->value = $date->format('Y-m-d\TH:i:s');
    $queuedJob->put('job', $registeredJob);
    $queuedJob->save();

    return $queuedJob;
  }

  /**
   * Set the Job failed with a reason, this function should be called from within the job itself,
   * it will raise a JobTerminateException and will cause the execution to terminate in a safe way.
   *
   * @param string $message
   * @return void
   */
  public final function setFailed(string $message) : void
  {
    throw new JobTerminateException($message);
  }

  /**
   * This function will be executed after execution. The status will be put on Completed, and the completion time will be filled in
   * In case the Job needs to be rescheduled, the rescheduling time will be calculated, and the new job will be inserted
   *
   * @return void
   */
  public final function postExecution() : void
  {
    // Lets not forget to switch back to the original user context
    $this->accountSwitcher->switchBack();

    // Lets put the job to completed
    $currentTime = gmdate('Y-m-d\TH:i:s');
    $this->print('Job with ID: '.$this->getId().' FINISHED at '.$currentTime . ' ('.$this->entity->title->value.')');

    if($this->entity->field_job_status->value === 'Running')
    {
      $this->entity->field_job_status->value = 'Completed';
    }

    $this->entity->field_end_time->value = $currentTime;
    $this->save();

    // And check if we need to reschedule this job
    $rescheduleIn = $this->entity->field_reschedule_in->value;
    $rescheduleFrom = $this->entity->field_reschedule_from->value;
    if(!empty($rescheduleIn) && $rescheduleIn > 0 && !empty($rescheduleFrom))
    {
      $newScheduledTime = null;
      $utc = new \DateTimeZone('UTC');
      $now = new \DateTime();
      $now->setTimezone($utc);
      $created = $now->format('U');

      if($rescheduleFrom === 'Scheduled Time')
      {
        $newScheduledTime = new \DateTime($this->entity->field_scheduled_time->value, $utc);
      }
      else if($rescheduleFrom === 'Start Time')
      {
        $newScheduledTime = new \DateTime($this->entity->field_start_time->value, $utc);
      }
      else if($rescheduleFrom === 'End Time')
      {
        $newScheduledTime = new \DateTime($this->entity->field_end_time->value, $utc);
      }

      if($newScheduledTime < $now)
      {
        $newScheduledTime = $now;
      }

      $newScheduledTime->modify('+'.$rescheduleIn.' minutes');

      $copiedJob = $this->getCopiedModel();
      $copiedJob->entity->field_end_time->value = null;
      $copiedJob->entity->field_start_time->value = null;
      $copiedJob->entity->field_error_message->value = null;
      $copiedJob->entity->created->value = $created;
      $copiedJob->entity->field_job_status->value = 'Queued';
      $copiedJob->entity->field_scheduled_time->value = $newScheduledTime->format('Y-m-d\TH:i:s');
      $copiedJob->save();

      $this->print('Job with ID: '.$copiedJob->getId().' RESCHEDULED at '.$newScheduledTime->format('Y-m-d\TH:i:s') . ' ('.$copiedJob->entity->title->value.')');
    }
  }

  /**
   * Sets the job failed, this method will be called from within the scheduler in case an Exception was raised.
   *
   * @param \Exception $ex
   * @param string $message
   * @return void
   */
  public final function failedExecution(?\Exception $ex = null, string $message = null) : void
  {
    // Execution failed, set the status to failed
    // Set a possible error message

    $currentTime = gmdate('Y-m-d\TH:i:s');
    $this->print('Job with ID: '.$this->getId().' FAILED at '.$currentTime . ' ('.$this->entity->title->value.')');

    $this->entity->field_job_status->value = 'Failed';
    $this->entity->field_end_time->value = $currentTime;

    if(!empty($ex))
    {
      $message = $ex->getMessage();
      if(!($ex instanceof JobTerminateException))
      {
        $message = '('.$message . ') ' . $ex->getTraceAsString();

        \Drupal::logger('spectrum_cron')->error($ex->getMessage());
        \Drupal::logger('spectrum_cron')->error($ex->getTraceAsString());
      }

      $this->entity->field_error_message->value = $message;
    }
    else if(!empty($message))
    {
      $this->entity->field_error_message->value = $message;
    }

    $this->save();
  }
}
