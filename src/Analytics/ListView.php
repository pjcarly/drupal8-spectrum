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
  /**
   * The entityType for this Model
   *
   * @var string
   */
  public static $entityType = 'query';

  /**
   * THe bundle for this Model
   *
   * @var string
   */
  public static $bundle = 'list_view';

  /**
   * The Relationships to other Models
   *
   * @return void
   */
  public static function relationships()
  {
    static::addRelationship(new ReferencedRelationship('conditions', 'Drupal\spectrum\Analytics\Condition', 'parent', ReferencedRelationship::$CASCADE_ON_DELETE));
    static::addRelationship(new ReferencedRelationship('sort_orders', 'Drupal\spectrum\Analytics\Order', 'parent', ReferencedRelationship::$CASCADE_ON_DELETE));
  }

  /**
   * Retruns a BundleQuery that can be used to query the DB with the provided conditions and Sort Orders
   *
   * @return BundleQuery
   */
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

  /**
   * Returns the FieldDefinition for the entity type of this Listview
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface
   */
  public function getDrupalFieldDefinitions() : \Drupal\Core\Field\FieldDefinitionInterface
  {
    $entityType = $this->entity->field_entity->value;
    $bundle = $this->entity->field_bundle->value;
    if(empty($bundle))
    {
      return \Drupal::service('entity_field.manager')->getFieldDefinitions($entityType, $entityType);
    }
    else
    {
      return \Drupal::service('entity_field.manager')->getFieldDefinitions($entityType, $bundle);
    }
  }
}
