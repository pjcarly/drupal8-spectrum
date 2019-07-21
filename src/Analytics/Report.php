<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Permissions\AccessPolicy\PublicAccessPolicy;

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

  /**
   * @inheritDoc
   */
  public static function getAccessPolicy(): AccessPolicyInterface {
    return new PublicAccessPolicy;
  }

}
