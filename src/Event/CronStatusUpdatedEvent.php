<?php

namespace Drupal\spectrum\Event;

use Drupal\spectrum\Runnable\QueuedJob;
use React\EventLoop\LoopInterface;
use Symfony\Contracts\EventDispatcher\Event;

final class CronStatusUpdatedEvent extends Event
{

  private QueuedJob $queuedJob;

  private int $current;

  private int $max;

  private ?LoopInterface $loop = null;

  public function __construct(
    QueuedJob $queuedJob,
    int $current,
    int $max,
    ?LoopInterface $loop = null
  ) {
    $this->queuedJob = $queuedJob;
    $this->current = $current;
    $this->max = $max;
    $this->loop = $loop;
  }

  public function getQueuedJob(): QueuedJob
  {
    return $this->queuedJob;
  }

  public function setQueuedJob(QueuedJob $queuedJob): self
  {
    $this->queuedJob = $queuedJob;
    return $this;
  }

  public function getCurrent(): int
  {
    return $this->current;
  }

  public function setCurrent(int $current): self
  {
    $this->current = $current;
    return $this;
  }

  public function getMax(): int
  {
    return $this->max;
  }

  public function setMax(int $max): self
  {
    $this->max = $max;
    return $this;
  }

  public function getLoop(): ?LoopInterface
  {
    return $this->loop;
  }

  public function setLoop(LoopInterface $loop): CronStatusUpdatedEvent
  {
    $this->loop = $loop;
    return $this;
  }
}
