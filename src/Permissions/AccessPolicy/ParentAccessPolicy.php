<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\Select;
use Drupal\groupflights\Services\ModelService;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\Relationship;
use Drupal\spectrum\Query\Condition;

/**
 * Class ParentAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessPolicy
 */
class ParentAccessPolicy implements AccessPolicyInterface {

  /**
   * @var string
   */
  const TABLE_ENTITY_ROOT = 'spectrum_entity_root';

  /**
   * @inheritDoc
   */
  public function onSave(Model $model): void {
    $roots = $this->getRootsForModel($model);

    $class = get_class($model);
    $tree = $this->childrenForClass($class, []);

    $values = [];

    foreach ($roots as $root) {
      if ($root::entityType() !== $model::entityType() || $root->getId() !== $model->getId()) {
        $values[] = strtr('(\'@entity_type\', @entity_id, \'@root_entity_type\', @root_entity_id)', [
          '@entity_type' => $model::entityType(),
          '@entity_id' => $model->getId(),
          '@root_entity_type' => $root::entityType(),
          '@root_entity_id' => $root->getId(),
        ]);
      }

      if ($values = $this->queryValues($tree, $class, $model, $root, $values)) {
        $columns = ['entity_type', 'entity_id', 'root_entity_type', 'root_entity_id'];
        $query = strtr('INSERT IGNORE INTO @table (@columns) VALUES @values', [
          '@table' => self::TABLE_ENTITY_ROOT,
          '@columns' => implode(', ', $columns),
          '@values' => implode(', ', $values)
        ]);
        \Drupal::database()->query($query)->execute();
      }
    }
  }

  /**
   * @inheritDoc
   */
  public function onDelete(Model $model): void {
//    $class = get_class($model);
//    $tree = $this->childrenForClass($class, []);
//
//    if ($values = $this->queryValues($tree, $class, $model, $model, [])) {
//      $columns = ['entity_type', 'entity_id', 'root_entity_type', 'root_entity_id'];
//      $query = strtr('DELETE FROM @table WHERE (@columns) IN (@values)', [
//        '@table' => self::TABLE_ENTITY_ROOT,
//        '@columns' => implode(', ', $columns),
//        '@values' => implode(', ', $values)
//      ]);
//      \Drupal::database()->query($query)->execute();
//    }
    \Drupal::database()
      ->delete(self::TABLE_ENTITY_ROOT)
      ->condition('root_entity_type', $model::entityType())
      ->condition('root_entity_id', (int)$model->getId())
      ->execute();
  }

  /**
   * @inheritDoc
   */
  public function userHasAccess(Model $model, int $uid): bool {
    $access = FALSE;

    $roots = $this->getRootsForModel($model);
    foreach ($roots as $root) {
      if ($root::getAccessPolicy()->userHasAccess($root, $uid)) {
        $access = TRUE;
        break;
      }
    }

    return $access;
  }

  /**
   * @param array $tree
   * @param string $class
   * @param \Drupal\spectrum\Model\Model $model
   * @param \Drupal\spectrum\Model\Model $root
   * @param array $queryValues
   *
   * @return array
   */
  protected function queryValues(
    array $tree,
    string $class,
    Model $model,
    Model $root,
    array $queryValues
  ): array {
    /**
     * @var Model $childModel
     * @var FieldRelationship $relationship
     */
    if (array_key_exists($class, $tree)) {
      foreach ($tree[$class] as $childModel => $relationship) {
        $query = $childModel::getModelQuery();
        $query->addCondition(new Condition(
          $relationship->relationshipField,
          '=',
          $model->getId()
        ));
        $collection = $query->fetchCollection();

        if ($collection->size() > 0) {
          /** @var Model $item */
          foreach ($collection as $item) {
            $queryValues[] = strtr('(\'@entity_type\', @entity_id, \'@root_entity_type\', @root_entity_id)', [
              '@entity_type' => $item->entity->getEntityType()->id(),
              '@entity_id' => $item->getId(),
              '@root_entity_type' => $root->entity->getEntityType()->id(),
              '@root_entity_id' => $root->getId(),
            ]);
            $queryValues = $this->queryValues(
              $tree,
              $childModel,
              $item,
              $root,
              $queryValues
            );
          }
        }

      }
    }

    return $queryValues;
  }

  /**
   * @inheritDoc
   */
  public function onQuery(Select $query): Select {
    $table = $query->getTables()['base_table']['table'];
    $condition = strtr('ser.entity_type = \'@type\' AND ser.entity_id = base_table.id', [
      '@type' => $table,
    ]);
    $query->innerJoin(self::TABLE_ENTITY_ROOT, 'ser', $condition);

    $condition = 'sea.entity_type = ser.root_entity_type AND sea.entity_id = ser.root_entity_id';
    $query->innerJoin('spectrum_entity_access', 'sea', $condition);

    $condition = new \Drupal\Core\Database\Query\Condition('OR');
    // Private access, see PrivateAccessPolicy.
    $condition->condition('sea.uid', \Drupal::currentUser()->id());
    // Public access, see PublicAccessPolicy.
    $condition->condition('sea.uid', 0);
    $query->condition($condition);

    return $query;
  }

  /**
   * @param \Drupal\spectrum\Model\Model $model
   *
   * @return array
   */
  protected function getRootsForModel(Model $model): array {
    $roots = [];

    $accessPolicy = $model::getAccessPolicy();
    if (!is_a($accessPolicy, ParentAccessPolicy::class)) {
      return [$model];
    }

    if ($parents = $this->parentModelsForModel($model)) {
      foreach ($parents as $parent) {
        $roots = array_merge($roots, $this->getRootsForModel($parent));
      }
    }
    else {
      new \RuntimeException;
    }

    return $roots;
  }

  /**
   * @param \Drupal\spectrum\Model\Model $model
   *
   * @return \Drupal\spectrum\Model\Model[]|null
   */
  protected function parentModelsForModel(Model $model): array {
    $parents = [];

    $parentRelationships = array_filter($model::getRelationships(), function (Relationship $relationship) {
      /** @var FieldRelationship $relationship */
      return is_a($relationship, FieldRelationship::class)
        && $relationship->getClass() !== NULL;
    });

    if (empty($parentRelationships)) {
      throw new \RuntimeException('No parent relationship found.');
    }
    else if (sizeof($parentRelationships) === 1) {
      if ($parent = $model->fetch($parentRelationships[0]->getName())) {
        $parents = [$parent];
      }
    }
    // In case there is more than one parent, check the parentRelationships in
    // descending order and take the first one. Let's always take the second
    // one here.
    else {
      $parentRelationships = array_reverse($parentRelationships);
      foreach ($parentRelationships as $p) {
        if ($parent = $model->fetch($p->getName())) {
          $parents[] = $parent;
        }
      }
    }

    return $parents;
  }

  /**
   * @param string $class
   *
   * @return array
   */
  public function getChildren(string $class, array $children): array {
    foreach ($children[$class] as $child => $relationship) {
      $children[$child] = $this->childrenForClass($child, $children);
    }

    return $children;
  }

  /**
   * @param string $class
   * @param array $children
   *
   * @return array
   */
  protected function childrenForClass(string $class, array $children): array {
    $models = (new ModelService)->getRegisteredModelClasses();

    /** @var Model $model */
    foreach ($models as $model) {
      $accessPolicy = $model::getAccessPolicy();
      if (!is_a($accessPolicy, ParentAccessPolicy::class)) {
        continue;
      }

      foreach ($model::getRelationships() as $relationship) {
        if (is_a($relationship, FieldRelationship::class)) {
        /** @var FieldRelationship $relationship*/
          $parent = $relationship->getClass();
          if (is_a($class, $parent, TRUE)) {
            $children[$class][$model] = $relationship;
            $children = $this->childrenForClass($model, $children);
          }
        }
      }
    }

    return $children;
  }

}