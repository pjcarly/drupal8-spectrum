<?php

namespace Drupal\spectrum\Runnable;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Permissions\AccessPolicy\PublicAccessPolicy;
use Drupal\spectrum\Runnable\RegisteredJob;
use Drupal\spectrum\Exceptions\JobTerminateException;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Models\User;

/**
 * A queued job is an implementation of RunnableModel, it can be scheduled to be executed on a later time.
 * QueuedJob itself shouldnt be instantiated. It should be extended with functionality
 * We cannot mark the class abstract, as on Query time for new QueuedJobs we dont know the Fully Qualified Classname of the implementation
 */
class QueuedJob extends RunnableModel
{
  const STATUS_QUEUED = 'Queued';
  const STATUS_RUNNING = 'Running';
  const STATUS_COMPLETED = 'Completed';
  const STATUS_FAILED = 'Failed';
  const RESCHEDULE_FROM_SCHEDULED_TIME = 'Scheduled Time';
  const RESCHEDULE_FROM_START_TIME = 'Start Time';
  const RESCHEDULE_FROM_END_TIME = 'End Time';

  /**
   * The entityType for this model
   *
   * @return string
   */
  public static function entityType(): string
  {
    return 'runnable';
  }

  /**
   * The Bundle for this Model
   *
   * @return string
   */
  public static function bundle(): string
  {
    return 'queued_job';
  }

  /**
   * @inheritDoc
   */
  public static function getAccessPolicy(): AccessPolicyInterface
  {
    return new PublicAccessPolicy;
  }

  /**
   * An instance of AccountSwitcher. This gives you the ability to execute the Job as another user, and switch back afterwards.
   *
   * @var Drupal\Core\Session\AccountSwitcherInterface
   */
  private $accountSwitcher;

  public static function relationships()
  {
    parent::relationships();
    static::addRelationship(new FieldRelationship('run_as', 'field_run_as.target_id'));
  }

  /**
   * This function will be executed just before starting the execution of the job.
   * Here the status will be set to Runninng and the start time will be set to the current time
   *
   * @return void
   */
  public final function preExecution(): void
  {
    $currentTime = new \DateTime('now', new \DateTimeZone('UTC'));
    $this->print('Job with ID: ' . $this->getId() . ' STARTED at ' . $currentTime->format('Y-m-d\TH:i:s') . ' (' . $this->getTitle() . ')');

    $this->setStatus(QueuedJob::STATUS_RUNNING);
    $this->setStartTime($currentTime);
    $this->setEndTime(null);
    $this->setErrorMessage(null);
    $this->save();

    // Check the user context we need to execute in, and switch to the provided user if necessary.
    // If no provided user, execute as anonymous

    $this->accountSwitcher = \Drupal::service('account_switcher');
    if (empty($this->getRunAsUserId()) || $this->getRunAsUserId() === 0 || empty($this->fetch('run_as'))) {
      $this->accountSwitcher->switchTo(new AnonymousUserSession());
    } else {
      $this->accountSwitcher->switchTo($this->getRunAsUser()->entity);
    }
  }

  /**
   * @return User|null
   */
  public function getRunAsUser(): ?User
  {
    return $this->get('run_as');
  }

  /**
   * @return integer|null
   */
  public function getRunAsUserId(): ?int
  {
    return $this->entity->{'field_run_as'}->target_id;
  }

  /**
   * @return string
   */
  public function getTitle(): string
  {
    return $this->entity->{'title'}->value;
  }

  /**
   * @param string $value
   * @return self
   */
  public function setTitle(string $value): QueuedJob
  {
    $this->entity->{'title'}->value = $value;
    return $this;
  }

  /**
   * @return string|null
   */
  public function getVariable(): ?string
  {
    return $this->entity->{'field_variable'}->value;
  }

  /**
   * @param string|null $value
   * @return self
   */
  public function setVariable(?string $value): QueuedJob
  {
    $this->entity->{'field_variable'}->value = $value;
    return $this;
  }

  /**
   * @return \DateTime|null
   */
  public function getStartTime(): ?\DateTime
  {
    if (empty($this->entity->{'field_start_time'}->value)) {
      return null;
    }

    return \DateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $this->entity->{'field_start_time'}->value, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
  }

  public function setStartTime(?\DateTime $value): QueuedJob
  {
    if (empty($value)) {
      $this->entity->{'field_start_time'}->value = null;
      return $this;
    }

    $value = clone $value;

    $this->entity->{'field_start_time'}->value = $value
      ->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    return $this;
  }

  /**
   * @return \DateTime|null
   */
  public function getEndTime(): ?\DateTime
  {
    if (empty($this->entity->{'field_end_time'}->value)) {
      return null;
    }

    return \DateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $this->entity->{'field_end_time'}->value, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
  }

  public function setEndTime(?\DateTime $value): QueuedJob
  {
    if (empty($value)) {
      $this->entity->{'field_end_time'}->value = null;
      return $this;
    }

    $value = clone $value;

    $this->entity->{'field_end_time'}->value = $value
      ->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    return $this;
  }

  /**
   * @return \DateTime|null
   */
  public function getScheduledTime(): ?\DateTime
  {
    if (empty($this->entity->{'field_scheduled_time'}->value)) {
      return null;
    }

    return \DateTime::createFromFormat(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, $this->entity->{'field_scheduled_time'}->value, new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
  }

  /**
   * @param \DateTime|null $value
   * @return QueuedJob
   */
  public function setScheduledTime(?\DateTime $value): QueuedJob
  {
    if (empty($value)) {
      $this->entity->{'field_scheduled_time'}->value = null;
      return $this;
    }

    $value = clone $value;

    $this->entity->{'field_scheduled_time'}->value = $value
      ->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    return $this;
  }

  /**
   * @return string|null
   */
  public function getErrorMessage(): ?string
  {
    return $this->entity->{'field_error_message'}->value;
  }

  /**
   * @param string|null $value
   * @return self
   */
  public function setErrorMessage(?string $value): QueuedJob
  {
    $this->entity->{'field_error_message'}->value = $value;
    return $this;
  }

  /**
   * @return string
   */
  public function getStatus(): string
  {
    return $this->entity->{'field_job_status'}->value;
  }

  /**
   * @param string $value
   * @return self
   */
  public function setStatus(string $value): QueuedJob
  {
    $this->entity->{'field_job_status'}->value = $value;
    return $this;
  }

  /**
   * @return int
   */
  public function getMinutesToFailure(): int
  {
    return $this->entity->{'field_minutes_to_failure'}->value;
  }

  /**
   * @param int $value
   * @return self
   */
  public function setMinutesToFailure(int $value): QueuedJob
  {
    $this->entity->{'field_minutes_to_failure'}->value = $value;
    return $this;
  }

  /**
   * @return int
   */
  public function shouldDeleteAfterCompletion(): bool
  {
    return $this->entity->{'field_delete_after_completion'}->value ?? false;
  }

  /**
   * @param int $value
   * @return self
   */
  public function setDeleteAfterCompletion(bool $value): QueuedJob
  {
    $this->entity->{'field_delete_after_completion'}->value = $value ? '1' : '0';
    return $this;
  }

  /**
   * @return int
   */
  public function getRescheduleIn(): ?int
  {
    return $this->entity->{'field_reschedule_in'}->value;
  }

  /**
   * @param int $value
   * @return self
   */
  public function setRescheduleIn(?int $value): QueuedJob
  {
    $this->entity->{'field_reschedule_in'}->value = $value;
    return $this;
  }

  /**
   * @return string|null
   */
  public function getRescheduleFrom(): ?string
  {
    return $this->entity->{'field_reschedule_from'}->value;
  }

  /**
   * @param string|null $value
   * @return self
   */
  public function setRescheduleFrom(?string $value): QueuedJob
  {
    $this->entity->{'field_reschedule_from'}->value = $value;
    return $this;
  }

  /**
   * Execute the job, this function should be overridden by every job, to provide an implementation
   *
   * @return void
   */
  public function execute(): void
  {
  }

  /**
   * Schedule a job on a given datetime, with a possible variable.
   *
   * @param string $jobName (required) The Name of the Job
   * @param string $variable (optional) Provide a variable for the job, it can be accessed on execution time
   * @param \DateTime $date (optional) The date you want to schedule the job on. If left blank, "now" will be chosen
   * @return QueuedJob
   */
  public static function schedule(string $jobName, string $variable = '', \DateTime $date = null): QueuedJob
  {
    $registeredJob = RegisteredJob::getByKey($jobName);

    if (empty($registeredJob)) {
      throw new \Exception('Registered Job (' . $jobName . ') not found');
    }

    if (empty($date)) {
      $date = new \DateTime('now', new \DateTimeZone('UTC'));
    }

    /** @var QueuedJob $queuedJob */
    $queuedJob = $registeredJob->createJobInstance();
    $queuedJob->setTitle($jobName);

    if (!empty($variable)) {
      $queuedJob->setVariable($variable);
    }

    $queuedJob->setMinutesToFailure(10);
    $queuedJob->setScheduledTime($date);
    $queuedJob->setRegisteredJob($registeredJob);
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
  public final function setFailed(string $message): void
  {
    throw new JobTerminateException($message);
  }

  /**
   * This function will be executed after execution. The status will be put on Completed, and the completion time will be filled in
   * In case the Job needs to be rescheduled, the rescheduling time will be calculated, and the new job will be inserted
   *
   * @return void
   */
  public final function postExecution(): void
  {
    // Lets not forget to switch back to the original user context
    $this->accountSwitcher->switchBack();

    // Lets put the job to completed
    $currentTime = new \DateTime('now', new \DateTimeZone('UTC'));
    $this->print(strtr('Job with ID: @jobId FINISHED at @finishedTime (@jobName)', [
      '@jobId' => $this->getId(),
      '@finishedTime' => $currentTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      '@jobName' => $this->getTitle()
    ]));

    if ($this->getStatus() === QueuedJob::STATUS_RUNNING) {
      $this->setStatus(QueuedJob::STATUS_COMPLETED);
    }

    $this->setEndTime($currentTime);
    $this->save();

    // And check if we need to reschedule this job
    $this->checkForReschedule();
    $this->checkForDeletion();
  }

  /**
   * Checks if the current Queued Job needs to be deleted after completion
   *
   * @return QueuedJob
   */
  private final function checkForDeletion(): QueuedJob
  {
    if ($this->shouldDeleteAfterCompletion() && ($this->getStatus() === self::STATUS_COMPLETED || $this->getStatus() === self::STATUS_FAILED)) {
      $this->delete();
    }

    return $this;
  }

  /**
   * Checks if the current Queued Job needs to be Rescheduled
   *
   * @return QueuedJob
   */
  private final function checkForReschedule(): QueuedJob
  {
    $rescheduleIn = $this->getRescheduleIn();
    $rescheduleFrom = $this->getRescheduleFrom();
    if (!empty($rescheduleIn) && $rescheduleIn > 0 && !empty($rescheduleFrom)) {

      $now = new \DateTime('now', new \DateTimeZone('UTC'));
      $newScheduledTime = null;

      if ($rescheduleFrom === QueuedJob::RESCHEDULE_FROM_SCHEDULED_TIME) {
        $newScheduledTime = $this->getScheduledTime();
      } else if ($rescheduleFrom === QueuedJob::RESCHEDULE_FROM_START_TIME) {
        $newScheduledTime = $this->getStartTime();
      } else if ($rescheduleFrom === QueuedJob::RESCHEDULE_FROM_END_TIME) {
        $newScheduledTime = $this->getEndTime();
      }

      if ($newScheduledTime < $now) {
        $newScheduledTime = $now;
      }

      $newScheduledTime->modify('+' . $rescheduleIn . ' minutes');

      /** @var QueuedJob $copiedJob */
      $copiedJob = $this->getCopiedModel();
      $copiedJob->setEndTime(null);
      $copiedJob->setStartTime(null);
      $copiedJob->setErrorMessage(null);
      $copiedJob->setCreatedDate($now);
      $copiedJob->setStatus(QueuedJob::STATUS_QUEUED);
      $copiedJob->setScheduledTime($newScheduledTime);
      $copiedJob->save();

      $this->print(strtr('Job with ID: @jobId RESCHEDULED at @finishedTime (@jobName)', [
        '@jobId' => $copiedJob->getId(),
        '@finishedTime' => $newScheduledTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
        '@jobName' => $copiedJob->getTitle()
      ]));
    }

    return $this;
  }

  /**
   * Sets the job failed, this method will be called from within the scheduler in case an Exception was raised.
   *
   * @param \Exception $ex
   * @param string $message
   * @return void
   */
  public final function failedExecution(?\Exception $ex = null, string $message = null): void
  {
    // Execution failed, set the status to failed
    // Set a possible error message

    $currentTime = new \DateTime('now', new \DateTimeZone('UTC'));

    $this->print(strtr('Job with ID: @jobId FAILED at @finishedTime (@jobName)', [
      '@jobId' => $this->getId(),
      '@finishedTime' => $currentTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      '@jobName' => $this->getTitle()
    ]));

    $this->setStatus(QueuedJob::STATUS_FAILED);
    $this->setEndTime($currentTime);

    if (!empty($ex)) {
      $message = $ex->getMessage();
      if (!($ex instanceof JobTerminateException)) {
        $message = '(' . $message . ') ' . $ex->getTraceAsString();

        \Drupal::logger('spectrum_cron')->error($ex->getMessage() . ' ' . $ex->getTraceAsString());
      }

      $this->setErrorMessage($message);
    } else if (!empty($message)) {
      $this->setErrorMessage($message);
    }

    $this->save();
    $this->checkForReschedule();
  }

  public final function runtimeError(\Error $error): void
  {
    // Execution failed, set the status to failed
    // Set a possible error message

    $currentTime = new \DateTime('now', new \DateTimeZone('UTC'));

    $this->print(strtr('Job with ID: @jobId generated RUNTIME ERROR at @finishedTime (@jobName)', [
      '@jobId' => $this->getId(),
      '@finishedTime' => $currentTime->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT),
      '@jobName' => $this->getTitle()
    ]));

    $this->setStatus(QueuedJob::STATUS_FAILED);
    $this->setEndTime($currentTime);

    $message = $error->getMessage();
    $message = '(' . $message . ') ' . $error->getTraceAsString();

    \Drupal::logger('spectrum_cron')->error($error->getMessage());
    \Drupal::logger('spectrum_cron')->error($error->getTraceAsString());

    $this->setErrorMessage($message);
    $this->save();
    $this->checkForReschedule();
  }

  /**
   * @return string|null
   */
  public function getRelatedBundle(): ?string
  {
    return $this->entity->{'field_related_bundle'}->value;
  }

  /**
   * @param string $value
   * @return $this
   */
  public function setRelatedBundle(string $value): self
  {
    $this->entity->{'field_related_bundle'}->value = $value;
    return $this;
  }

  /**
   * @return string|null
   */
  public function getRelatedEntity(): ?string
  {
    return $this->entity->{'field_related_entity'}->value;
  }

  /**
   * @param string $value
   * @return $this
   */
  public function setRelatedEntity(string $value): self
  {
    $this->entity->{'field_related_entity'}->value = $value;
    return $this;
  }

  /**
   * @return string|null
   */
  public function getRelatedModelId(): ?string
  {
    return $this->entity->{'field_related_model_id'}->value;
  }

  /**
   * @param string $value
   * @return $this
   */
  public function setRelatedModelId(string $value): self
  {
    $this->entity->{'field_related_model_id'}->value = $value;
    return $this;
  }
}
