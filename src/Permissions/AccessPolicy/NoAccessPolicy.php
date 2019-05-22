<?php

namespace Drupal\spectrum\Permissions\AccessStrategy;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\spectrum\Model\Model;

/**
 * Class NoAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessStrategy
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
  public function onQuery(AlterableInterface $query): AlterableInterface {
    // TODO: Implement onQuery() method.
    return $query->addTag('access:' . self::class);
  }

}
