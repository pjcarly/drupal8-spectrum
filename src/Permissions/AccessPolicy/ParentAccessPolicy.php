<?php

namespace Drupal\spectrum\Permissions\AccessStrategy;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\spectrum\Model\Model;

/**
 * Class ParentAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessStrategy
 */
class ParentAccessPolicy implements AccessPolicyInterface {

  /**
   * @inheritDoc
   */
  public function onSave(Model $model): void {
    // TODO: Implement onSave() method.
  }

  /**
   * @inheritDoc
   */
  public function onQuery(AlterableInterface $query): AlterableInterface {
    // TODO: Implement onQuery() method.
    return $query;
  }

}