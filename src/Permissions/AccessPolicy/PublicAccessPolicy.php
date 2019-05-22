<?php

namespace Drupal\spectrum\Permissions\AccessStrategy;

use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\spectrum\Model\Model;

/**
 * Class PublicAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessStrategy
 */
class PublicAccessPolicy implements AccessPolicyInterface {

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
    // Do nothing.
    return $query;
  }

}
