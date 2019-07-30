<?php

namespace Drupal\spectrum\Analytics;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Permissions\AccessPolicy\PublicAccessPolicy;
use Drupal\spectrum\Query\BundleQuery;
use Drupal\spectrum\Query\Condition;
use Drupal\spectrum\Query\Order;

class ListView extends Model
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
    return 'list_view';
  }

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
   * @inheritDoc
   */
  public static function getAccessPolicy(): AccessPolicyInterface
  {
    return new PublicAccessPolicy;
  }

  /**
   * Retruns a BundleQuery that can be used to query the DB with the provided conditions and Sort Orders
   *
   * @return BundleQuery
   */
  public function buildQuery(): BundleQuery
  {
    $query = new BundleQuery($this->entity->field_entity->value, $this->entity->field_bundle->value);
    foreach ($this->conditions as $condition) {
      $query->addCondition($condition->buildQueryCondition());
    }
    foreach ($this->sort_orders as $sortOrder) {
      $query->addSortOrder($sortOrder->buildQueryOrder());
    }
    return $query;
  }

  /**
   * Returns the FieldDefinitions for the entity type of this Listview
   *
   * @return array
   */
  public function getDrupalFieldDefinitions(): array
  {
    $entityType = $this->entity->field_entity->value;
    $bundle = $this->entity->field_bundle->value;
    if (empty($bundle)) {
      return \Drupal::service('entity_field.manager')->getFieldDefinitions($entityType, $entityType);
    } else {
      return \Drupal::service('entity_field.manager')->getFieldDefinitions($entityType, $bundle);
    }
  }
}
