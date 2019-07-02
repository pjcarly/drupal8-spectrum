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
   * @inheritDoc
   */
  public function onSave(Model $model): void {
    $root = $this->getRootForModel($model);

    $class = get_class($model);
    $tree = $this->childrenForClass($class, []);

    if ($values = $this->insertQueryValues($tree, $class, $model, $root, [])) {
      $columns = ['entity_type', 'entity_id', 'root_entity_type', 'root_entity_id'];
      $query = strtr('INSERT IGNORE INTO @table (@columns) VALUES @values', [
        '@table' => 'spectrum_entity_root',
        '@columns' => implode(', ', $columns),
        '@values' => implode(', ', $values)
      ]);
      \Drupal::database()->query($query)->execute();
    }
  }

  /**
   * @param array $tree
   * @param string $class
   * @param \Drupal\spectrum\Model\Model $model
   * @param \Drupal\spectrum\Model\Model $root
   * @param array $insertQueryValues
   *
   * @return array
   */
  protected function insertQueryValues(
    array $tree,
    string $class,
    Model $model,
    Model $root,
    array $insertQueryValues
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
            $insertQueryValues[] = strtr('(\'@entity_type\', @entity_id, \'@root_entity_type\', @root_entity_id)', [
              '@entity_type' => $item->entity->getEntityType()->id(),
              '@entity_id' => $item->getId(),
              '@root_entity_type' => $root->entity->getEntityType()->id(),
              '@root_entity_id' => $root->getId(),
            ]);
            $insertQueryValues = $this->insertQueryValues(
              $tree,
              $childModel,
              $item,
              $root,
              $insertQueryValues
            );
          }
        }

      }
    }

    return $insertQueryValues;
  }

  /**
   * @inheritDoc
   */
  public function onQuery(Select $query): Select {
    $table = $query->getTables()['base_table']['table'];
    $condition = strtr('ser.entity_type = \'@type\' AND ser.entity_id = base_table.id', [
      '@type' => $table,
    ]);
    $query->innerJoin('spectrum_entity_root', 'ser', $condition);

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
   * @return \Drupal\spectrum\Model\Model|null
   */
  protected function getRootForModel(Model $model): ?Model {
    $accessPolicy = $model::getAccessPolicy();
    if (!is_a($accessPolicy, ParentAccessPolicy::class)) {
      return $model;
    }

    $parent = $this->parentModelForModel($model);

    if ($parent !== NULL) {
      $parent = $this->getRootForModel($parent);
    }
    else {
      new \RuntimeException;
    }

    return $parent;
  }

  /**
   * @param \Drupal\spectrum\Model\Model $model
   *
   * @return \Drupal\spectrum\Model\Model|null
   */
  protected function parentModelForModel(Model $model): ?Model {
    $parents = array_filter($model::getRelationships(), function (Relationship $relationship) {
      /** @var FieldRelationship $relationship */
      return is_a($relationship, FieldRelationship::class)
        && $relationship->getClass() !== NULL;
    });

    usort($parents, function (FieldRelationship $a, FieldRelationship $b) {
      return $a->getParentPriority() <=> $b->getParentPriority();
    });

    if (empty($parents)) {
      throw new \RuntimeException('No parent relationship found.');
    }
    else if (sizeof($parents) === 1) {
      return $model->fetch($parents[0]->getName());
    }
    // In case there is more than one parent, check the parents in
    // descending order and take the first one. Let's always take the second
    // one here.
    else {
      $parents = array_reverse($parents);
      foreach ($parents as $p) {
        if ($m = $model->fetch($p->getName())) {
          return $m;
        }
      }
    }

    return NULL;
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