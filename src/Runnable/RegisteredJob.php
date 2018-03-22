<?php
namespace Drupal\spectrum\Runnable;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Model\ReferencedRelationship;

class RegisteredJob extends Model
{
  protected $cliContext = true;

  public static $entityType = 'runnable';
  public static $bundle = 'registered_job';
  public static $idField = 'id';

  public static $plural = 'Registered Jobs';

  public static function relationships()
  {
    parent::relationships();
    static::addRelationship(new ReferencedRelationship('queued_jobs', 'Drupal\spectrum\Runnable\QueuedJob', 'job'));
  }

  public final function singleRun()
  {
    $class = $this->entity->field_class->value;

    if(!class_exists($class))
    {
      $this->print('Class does not exist');
      return;
    }

    try
    {
      $job = $class::createNew();
      $job->setCliContext($this->cliContext);
      $job->execute();
    }
    catch(\Exception $ex)
    {
      $message = 'Runnable execution failed ('.$job->entity->field_class->value.'): '.$ex->getMessage();
      $this->print($message);
      \Drupal::logger('spectrum_cron')->error($message);
    }
  }

  public function print($message)
  {
    if($this->cliContext)
    {
      drush_print($message);
    }
    else
    {
      print($message.'<br/>');
    }
  }

  public function setCliContext($cliContext)
  {
    $this->cliContext = $cliContext;
  }

  public static function getByKey(string $key)
  {
    $query = static::getModelQuery();
    $query->addCondition(new Condition('title', '=', $key));
    $query->setLimit(1);

    return $query->fetchSingleModel();
  }
}
