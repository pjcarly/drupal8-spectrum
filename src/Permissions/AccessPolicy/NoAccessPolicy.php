<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Database\Query\Select;
use Drupal\spectrum\Model\Model;

/**
 * Class NoAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessPolicy
 */
class NoAccessPolicy implements AccessPolicyInterface {

  /**
   * @inheritDoc
   */
  public function onSave(Model $model): void {
    // Do nothing.
  }

  /**
   * @inheritDoc
   */
  public function onDelete(Model $model): void {
    // Do nothing.
  }

  /**
   * @inheritDoc
   */
  public function onQuery(Select $query): Select {
    $query->addExpression('1=0');
  }

}
