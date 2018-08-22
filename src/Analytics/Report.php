<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

class Report extends Model
{
  /**
   * The entityType for this model
   *
   * @return string
   */
  public static function entityType() : string
  {
    return 'query';
  }

  /**
   * The Bundle for this Model
   *
   * @return string
   */
  public static function bundle() : string
  {
    return 'report';
  }

  /**
   * The Relationships to other models
   *
   * @return void
   */
  public static function relationships()
  {

  }
}
