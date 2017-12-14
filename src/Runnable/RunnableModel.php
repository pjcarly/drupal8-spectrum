<?php
namespace Drupal\spectrum\Runnable;

use Drupal\spectrum\Model\Model;

abstract class RunnableModel extends Model
{
  protected $cliContext = true;

  public final function run()
  {
    try
    {
      $this->preExecution();
      $this->execute();
      $this->postExecution();
    }
    catch(\Exception $ex)
    {
      $message = 'Runnable execution failed ('.$this->entity->field_class->value.'): '.$ex->getMessage();
      drush_print($message);
      \Drupal::logger('spectrum_cron')->error($message);
    }
  }

  public final function singleRun()
  {
    try
    {
      $this->execute();
    }
    catch(\Exception $ex)
    {
      $message = 'Runnable execution failed ('.$this->entity->field_class->value.'): '.$ex->getMessage();
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

  abstract function preExecution();
  abstract function execute();
  abstract function postExecution();
  abstract function failedExecution(\Exception $ex = null, $message = null);
}
