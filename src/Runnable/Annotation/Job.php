<?php

namespace Drupal\spectrum\Runnable\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an Job annotation object.
 *
 * @Annotation
 *
 * @see \Drupal\spectrum\Services\JobService
 * @see \Drupal\spectrum\Runnable\JobInterface
 *
 * @ingroup spectrum
 */
class Job extends Plugin
{
  /**
   * The ID of the Job
   *
   * @var string
   */
  public $id;

  /**
   * A description of Job.
   *
   * @var string
   */
  public $description;
}
