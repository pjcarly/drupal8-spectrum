<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\Select;
use Drupal\spectrum\Model\Model;

/**
 * Class PublicAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessPolicy
 */
class PublicAccessPolicy implements AccessPolicyInterface {

  /**
   * @inheritDoc
   */
  public function onSave(Model $model): void {
    // Set the root model for all children.
    (new ParentAccessPolicy)->onSave($model);
  }

  /**
   * @inheritDoc
   */
  public function onQuery(Select $query): Select {
    return $query;
  }

}
