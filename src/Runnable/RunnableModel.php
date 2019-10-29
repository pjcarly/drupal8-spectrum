<?php

namespace Drupal\spectrum\Runnable;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * The standard implementation class of All Runnable models. The scheduler will call the run() method on this class.
 * Every implementation should provide implement preExecution, execute, postExecution, failedExecution and runtimeError
 */
abstract class RunnableModel extends Model
{
  /**
   * @var OutputInterface
   */
  protected $output;

  /**
   * A way to mark the Context as CLI or not. Based on this, a different print will be done
   *
   * @var boolean
   */
  protected $cliContext = true;

  public static function relationships()
  {
    parent::relationships();
    static::addRelationship(new FieldRelationship('job', 'field_job.target_id'));
  }

  /**
   * @return RegisteredJob
   */
  public function getRegisteredJob(): RegisteredJob
  {
    return $this->get('job');
  }

  /**
   * @param RegisteredJob $value
   * @return self
   */
  public function setRegisteredJob(RegisteredJob $value): RunnableModel
  {
    $this->put('job', $value);
    return $this;
  }

  /**
   * @param OutputInterface $output
   * @return self
   */
  public function setOutput(OutputInterface $output): RunnableModel
  {
    $this->output = $output;
    return $this;
  }

  /**
   * @return OutputInterface
   */
  public function getOutput(): OutputInterface
  {
    return $this->output;
  }

  /**
   * This function will execute the implementation methods in correct order.
   *
   * @return void
   */
  public final function run(): void
  {
    try {
      $this->fetch('job');

      $this->preExecution();
      $this->execute();
      $this->postExecution();
    } catch (\Exception $ex) {
      $this->failedExecution($ex);
    } catch (\Error $error) {
      $this->runtimeError($error);
    }
  }

  /**
   * A helper method to print something, either to the CLI out, or with regular print()
   *
   * @param string $message
   * @return void
   */
  public function print(string $message): void
  {
    $this->output->writeln($message);
  }

  /**
   * Set whether this is a CLI context or not
   *
   * @param boolean $cliContext
   * @return RunnableModel
   */
  public function setCliContext($cliContext): RunnableModel
  {
    $this->cliContext = $cliContext;
    return $this;
  }

  abstract function preExecution(): void;
  abstract function execute(): void;
  abstract function postExecution(): void;
  abstract function failedExecution(\Exception $ex = null, string $message = null): void;
  abstract function runtimeError(\Error $error): void;
}
