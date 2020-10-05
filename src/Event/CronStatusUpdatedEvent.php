<?php

namespace Drupal\spectrum\Event;

use Drupal\spectrum\Runnable\QueuedJob;
use Symfony\Component\EventDispatcher\Event;

class CronStatusUpdatedEvent extends Event
{

  protected QueuedJob $queuedJob;
  protected int $current;
  protected int $max;

  public function __construct(QueuedJob $queuedJob, int $current, int $max)
  {
    $this->queuedJob = $queuedJob;
    $this->current = $current;
    $this->max = $max;
  }

  /**
   * @return QueuedJob
   */
  public function getQueuedJob(): QueuedJob
  {
    return $this->queuedJob;
  }

  /**
   * @param QueuedJob $queuedJob
   * @return CronStatusUpdatedEvent
   */
  public function setQueuedJob(QueuedJob $queuedJob): self
  {
    $this->queuedJob = $queuedJob;
    return $this;
  }

  /**
   * @return int
   */
  public function getCurrent(): int
  {
    return $this->current;
  }

  /**
   * @param int $current
   * @return CronStatusUpdatedEvent
   */
  public function setCurrent(int $current): self
  {
    $this->current = $current;
    return $this;
  }

  /**
   * @return int
   */
  public function getMax(): int
  {
    return $this->max;
  }

  /**
   * @param int $max
   * @return CronStatusUpdatedEvent
   */
  public function setMax(int $max)
  {
    $this->max = $max;
    return $this;
  }
}
