<?php
namespace Drupal\spectrum\Runnable;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;

abstract class RunnableModel extends Model
{
  protected $cliContext = true;

  public static function relationships()
  {
    parent::relationships();
    static::addRelationship(new FieldRelationship('job', 'field_job.target_id'));
  }

  public final function run()
  {
    $currentTime = gmdate('Y-m-d\TH:i:s');
    try
    {
      $job = $this->fetch('job');

      $this->preExecution();
      $this->execute();
      $this->postExecution();
    }
    catch(\Exception $ex)
    {
      $this->failedExecution($ex);
      \Drupal::logger('spectrum_cron')->error($ex->getMessage());
      \Drupal::logger('spectrum_cron')->error($ex->getTraceAsString());
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

  abstract function preExecution();
  abstract function execute();
  abstract function postExecution();
  abstract function failedExecution(\Exception $ex = null, $message = null);
}
