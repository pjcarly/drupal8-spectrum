<?php

namespace Drupal\spectrum\Permissions\AccessPolicy;

use Drupal\Core\Database\Query\Select;
use Drupal\mist_crm\Models\Object\Company;
use Drupal\spectrum\Exceptions\RelationshipNotDefinedException;
use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\Model;

/**
 * Class PrivateAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessPolicy
 */
class PrivateAccessPolicy implements AccessPolicyInterface {

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userStorage;

  /**
   * PrivateAccessPolicy constructor.
   */
  public function __construct() {
    $this->database = \Drupal::database();
    $this->userStorage = \Drupal::entityTypeManager()
      ->getStorage('user');
  }

  /**
   * @inheritDoc
   */
  public function onSave(Model $model): void {
    // Create an insert query. This query will be used to insert all
    // permissions at once.
    $insertQuery = $this->database->insert(self::TABLE_ENTITY_ACCESS);
    $insertQuery->fields(['entity_type', 'entity_id', 'uid']);

    foreach ($this->getUserIds($model) as $uid) {
      $insertQuery->values([
        'entity_type' => $model::entityType(),
        'entity_id' => $model->getId(),
        'uid' => $uid,
      ]);
    }

    // Delete all current permissions.
    $this->removeAccess($model);

    $insertQuery->execute();

    // Set the root model for all children.
    (new ParentAccessPolicy)->onSave($model);
  }

  /**
   * @inheritDoc
   */
  public function onDelete(Model $model): void {
    $this->removeAccess($model);
    (new ParentAccessPolicy)->onDelete($model);
  }

  /**
   * @param \Drupal\spectrum\Model\Model $model
   */
  protected function removeAccess(Model $model): void {
    $this->database->delete(self::TABLE_ENTITY_ACCESS)
      ->condition('entity_type', $model::entityType())
      ->condition('entity_id', $model->getId())
      ->execute();
  }

  /**
   * @inheritDoc
   */
  public function onQuery(Select $query): Select {
    $type = $query->getTables()['base_table']['table'];
    $condition = strtr('ea.entity_type = \'@type\' AND ea.entity_id = base_table.id', [
      '@type' => $type,
    ]);
    $query->innerJoin(self::TABLE_ENTITY_ACCESS, 'ea', $condition);
    $query->condition('ea.uid', \Drupal::currentUser()->id());

    return $query;
  }

  /**
   * @param \Drupal\spectrum\Model\Model $model
   *
   * @return array
   */
  protected function getUserIds(Model $model): array {
    $users = [];

    // Fetches the user IDs related to a company.
    $usersFromCompany = function (Company $company): array {
      $company->fetch('contacts');
      return $company->fetch('contacts.user')->getIds();
    };

    try {
      // There is a contact related to the model.
      /** @var \Drupal\mist_crm\Models\Object\Contact $contact */
      if ($contact = $model->fetch('contact')) {

        // If there is a company related to the contact, insert permissions for
        // that company's employees.
        /** @var Company $company */
        if ($company = $contact->fetch('company')) {
          $users = array_merge($users, $usersFromCompany($company));
        }

        // If there is no company related to the contact, but there is a user
        // related to the contact, insert permissions for that user.
        /** @var \Drupal\mist_crm\Models\User $user */
        else if ($user = $contact->fetch('user')) {
          $users = array_merge($users, [$user->getId()]);
        }
      }
    }
    catch (RelationshipNotDefinedException $e) {
    }

    try {
      // There is a company related to the model.
      /** @var \Drupal\mist_crm\Models\Object\Company $company */
      if ($company = $model->fetch('company')) {
        $users = array_merge($users, $usersFromCompany($company));
      }
    }
    catch (RelationshipNotDefinedException $e) {
    }

    try {
      // There is an organization related to the model.
      /** @var \Drupal\mist_crm\Models\Organization\Organization $organization */
      if ($organization = $model->fetch('organization')) {
        $employees = $organization->fetch('users');
        if (is_a($employees, Collection::class)) {
          $users = array_merge($users, $employees->getIds());
        }
      }
    }
    catch (RelationshipNotDefinedException $e) {
    }

    return array_unique($users);
  }

}
