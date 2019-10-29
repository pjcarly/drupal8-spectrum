<?php

namespace Drupal\spectrum\Runnable;

interface BatchableInterface
{
  /**
   * Returns the next batch of records to process
   *
   * @return array
   */
  public function getBatchGenerator(): \Generator;

  /**
   * Returns the total amount of records that will pass through the batch job.
   * Returns NULl when the implementation doesnt know
   *
   * @return integer|null
   */
  public function getTotalBatchedRecords(): ?int;

  /**
   * This will be called before starting batches, to let the implementation know per how many records the batches should be given to the getNextBatch() function
   *
   * @param integer $batchSize
   * @return BatchableInterface
   */
  public function setBatchSize(int $batchSize): BatchableInterface;
}
