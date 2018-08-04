<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

use Drupal\spectrum\Query\Order as QueryOrder;

class Order extends Model
{
  public static $entityType = 'query';
  public static $bundle = 'order';
  public static $idField = 'id';

  public static $plural = 'Sort Orders';

  public static function relationships()
  {
    static::addRelationship(new FieldRelationship('parent', 'field_parent.target_id'));
  }

  /**
   * Returns a Spectrum Query Order based on the values filled in that can be used in a Query to define your Order
   *
   * @return QueryOrder
   */
  public function buildQueryOrder() : QueryOrder
  {
    return new QueryOrder($this->entity->field_field->value, $this->entity->field_direction->value);
  }
}
