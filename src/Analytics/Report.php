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
   * The ID field of this Model
   *
   * @var string
   */
  public static $idField = 'id';

  /**
   * The plural description of this model
   *
   * @var string
   */
  public static $plural = 'Reports';

  /**
   * The Relationships to other models
   *
   * @return void
   */
  public static function relationships()
  {

  }
}
