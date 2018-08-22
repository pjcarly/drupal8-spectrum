<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

class Report extends Model
{
  /**
   * The entityType of this Model
   *
   * @var string
   */
  public static $entityType = 'query';

  /**
   * The Bundle of this Model
   *
   * @var string
   */
  public static $bundle = 'report';

  /**
   * The Relationships to other models
   *
   * @return void
   */
  public static function relationships()
  {

  }
}
