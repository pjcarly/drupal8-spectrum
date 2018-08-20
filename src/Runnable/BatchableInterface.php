<?php

namespace Drupal\spectrum\Runnable;

interface BatchableInterface
{
  public function getNextBatch() : array;

  public function setBatchSize(int $batchSize) : BatchableInterface;
}
