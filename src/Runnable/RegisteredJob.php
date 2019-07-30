<?php

namespace Drupal\spectrum\Runnable;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Permissions\AccessPolicy\PublicAccessPolicy;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Model\ReferencedRelationship;

/**
 * A registered Job is a way to Register Fully Qualified Classnames in the system, pointing to a QueuedJob. A registered job can be activated or disabled
 */
class RegisteredJob extends Model
{
  /**
   * A way to mark the Context as CLI or not. Based on this, a different print will be done
   *
   * @var boolean
   */
  protected $cliContext = true;

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
    return 'registered_job';
  }

  public static function relationships()
  {
    parent::relationships();
    static::addRelationship(new ReferencedRelationship('queued_jobs', 'Drupal\spectrum\Runnable\QueuedJob', 'job'));
  }

  /**
   * @inheritDoc
   */
  public static function getAccessPolicy(): AccessPolicyInterface
  {
    return new PublicAccessPolicy;
  }

  /**
   * Execute the Registered Job a single time. You can call this method without needing to schedule the job
   *
   * @return void
   */
  public final function singleRun(): void
  {
    try {
      $job = $this->createJobInstance();
      $job->setCliContext($this->cliContext);
      $job->execute();
    } catch (\Exception $ex) {
      $message = 'Runnable execution failed (' . $job->entity->field_class->value . '): ' . $ex->getMessage();
      $this->print($message);
      \Drupal::logger('spectrum_cron')->error($message);
    }
  }

  /**
   * Create a new Instance of the a Job you want to schedule. This job must extend from RunnableModel
   *
   * @return RunnableModel
   */
  public final function createJobInstance(): RunnableModel
  {
    $class = $this->entity->field_class->value;

    if (!class_exists($class)) {
      throw new \Exception('Class does not exist');
    }

    return $class::forgeNew();
  }

  /**
   * A helper method to print something, either to the CLI out, or with regular print()
   *
   * @param string $message
   * @return void
   */
  public function print(string $message): void
  {
    if ($this->cliContext) {
      drush_print($message);
    } else {
      print($message . '<br/>');
    }
  }

  /**
   * Set whether this is a CLI context or not
   *
   * @param boolean $cliContext
   * @return RegisteredJob
   */
  public function setCliContext(bool $cliContext): RegisteredJob
  {
    $this->cliContext = $cliContext;
    return $this;
  }

  /**
   * Find a registered job by its unique key
   *
   * @param string $key
   * @return RegisteredJob|null
   */
  public static function getByKey(string $key): ?RegisteredJob
  {
    $query = static::getModelQuery();
    $query->addCondition(new Condition('title', '=', $key));
    $query->setLimit(1);

    return $query->fetchSingleModel();
  }
}
