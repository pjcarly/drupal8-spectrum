<?php
namespace Drupal\spectrum\Runnable;

use Drupal\spectrum\Model\Model;

abstract class RunnableModel extends Model
{
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
      \Drupal::logger('spectrum_cron')->error('Runnable execution failed ('.$this->entity->field_class->value.'): '.$ex->getMessage());
    }
  }

  abstract function preExecution();
  abstract function execute();
  abstract function postExecution();
  abstract function failedExecution(\Exception $ex = null);
}
