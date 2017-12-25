<?php
namespace Drupal\spectrum\Runnable;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Session\AccountSwitcher;

class QueuedJob extends RunnableModel
{
	public static $entityType = 'runnable';
	public static $bundle = 'queued_job';
	public static $idField = 'id';

  public static $plural = 'Queued Jobs';

  private $accountSwitcher;

  public static function relationships()
	{

  }

  public final function preExecution()
  {
    $this->entity->field_job_status->value = 'Running';
    $this->entity->field_start_time->value = gmdate('Y-m-d\TH:i:s');
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

  public function execute()
  {

  }

  public final function postExecution()
  {
    // Lets not forget to switch back to the original user context
    $this->accountSwitcher->switchBack();

    // Lets put the job to completed
    $this->entity->field_job_status->value = 'Completed';
    $this->entity->field_end_time->value = gmdate('Y-m-d\TH:i:s');
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
    }
  }

  public final function failedExecution(\Exception $ex = null, $message = null)
  {
    // Execution failed, set the status to failed
    // Set a possible error message

    $this->entity->field_job_status->value = 'Failed';
    $this->entity->field_end_time->value = gmdate('Y-m-d\TH:i:s');
    if(!empty($ex))
    {
      $this->entity->field_error_message->value = $ex->getMessage();
    }
    else if(!empty($message))
    {
      $this->entity->field_error_message->value = $message;
    }
    $this->save();
  }
}