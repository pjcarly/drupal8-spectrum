<?php
namespace Drupal\spectrum\Jobs;

use Drupal\spectrum\Runnable\QueuedJob;
use Drupal\spectrum\Query\Condition;
use Drupal\groupflights\Models\Node\Airline;
use Drupal\file\Entity\File;

class QueuedJobJanitorJob extends QueuedJob
{
  public function execute()
  {
    $variable = json_decode($this->entity->field_variable->value);

    if(!empty($variable) && isset($variable->maxAgeInDays) && $variable->maxAgeInDays > 0)
    {
      $utc = new \DateTimeZone('UTC');
      $now = new \DateTime();
      $now->setTimezone($utc);
      $now->sub(new \DateInterval('P'.$variable->maxAgeInDays.'D'));

      $query = QueuedJob::getModelQuery();
      $query->addCondition(new Condition('field_scheduled_time', '<', $now->format('Y-m-d\TH:i:s')));
      $queuedJobs = $query->fetchCollection();

      if(!empty($queuedJobs))
      {
        $queuedJobs->removeAll();
        $queuedJobs->save();
      }
    }
  }
}
