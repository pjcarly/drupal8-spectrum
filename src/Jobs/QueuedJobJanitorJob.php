<?php

namespace Drupal\spectrum\Jobs;

use Drupal\Core\Entity\EntityInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\spectrum\Runnable\QueuedJob;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Runnable\BatchableInterface;
use Drupal\spectrum\Runnable\BatchJob;

/**
 * This job will remove all queuedjob records from the database which are older than maxAgeInDays (which should be added to the variable)
 */
class QueuedJobJanitorJob extends BatchJob
{
  protected function getBatchable(): BatchableInterface
  {
    $variable = json_decode($this->getVariable());

    if (empty($variable) || !isset($variable->maxAgeInDays) || $variable->maxAgeInDays < 0) {
      $this->setFailed('Missing JSON object in variable, with the key "maxAgeInDays" and value the amount of days you want to save');
    }


    $now = new \DateTime('now', new \DateTimeZone('UTC'));
    $now->sub(new \DateInterval('P' . $variable->maxAgeInDays . 'D'));

    $query = QueuedJob::getModelQuery();
    $query->addCondition(new Condition('field_scheduled_time', '<', $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT)));

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  protected function process(EntityInterface $entity): void
  {
    $queuedJob = QueuedJob::forgeByEntity($entity);
    $queuedJob->delete();
  }
}
