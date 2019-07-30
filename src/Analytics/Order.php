<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Permissions\AccessPolicy\PublicAccessPolicy;
use Drupal\spectrum\Query\Order as QueryOrder;

class Order extends Model
{
  /**
   * The entityType for this model
   *
   * @return string
   */
  public static function entityType(): string
  {
    return 'query';
  }

  /**
   * The Bundle for this Model
   *
   * @return string
   */
  public static function bundle(): string
  {
    return 'order';
  }

  /**
   * The Relationships to other Models
   *
   * @return void
   */
  public static function relationships()
  {
    static::addRelationship(new FieldRelationship('parent', 'field_parent.target_id'));
  }

  /**
   * @inheritDoc
   */
  public static function getAccessPolicy(): AccessPolicyInterface
  {
    return new PublicAccessPolicy;
  }

  /**
   * Returns a Spectrum Query Order based on the values filled in that can be used in a Query to define your Order
   *
   * @return QueryOrder
   */
  public function buildQueryOrder(): QueryOrder
  {
    return new QueryOrder($this->entity->field_field->value, $this->entity->field_direction->value);
  }
}
