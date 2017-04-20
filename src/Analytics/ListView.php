<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

use Drupal\spectrum\Query\BundleQuery;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Query\Order;

class ListView extends Model
{
	public static $entityType = 'query';
	public static $bundle = 'list_view';
	public static $idField = 'id';

  public static $plural = 'List Views';

  public static function relationships()
	{
    static::addRelationship(new ReferencedRelationship('conditions', 'Drupal\spectrum\Analytics\Condition', 'parent'));
    static::addRelationship(new ReferencedRelationship('sort_orders', 'Drupal\spectrum\Analytics\Order', 'parent'));
	}

  public function buildQuery() : BundleQuery
  {
    $query = new BundleQuery($this->entity->field_entity->value, $this->entity->field_bundle->value);
    foreach($this->conditions as $condition)
    {
      $query->addCondition($condition->buildQueryCondition());
    }
    foreach($this->sort_orders as $sortOrder)
    {
      $query->addSortOrder($sortOrder->buildQueryOrder());
    }
    return $query;
  }
}
