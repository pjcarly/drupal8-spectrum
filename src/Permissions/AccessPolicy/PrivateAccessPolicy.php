<?php

namespace Drupal\spectrum\Permissions\AccessStrategy;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\AlterableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\spectrum\Model\Model;

/**
 * Class PrivateAccessPolicy
 *
 * @package Drupal\spectrum\Permissions\AccessStrategy
 */
class PrivateAccessPolicy implements AccessPolicyInterface {

  /**
   * @var string
   */
  const TABLE_ENTITY_ACCESS = 'spectrum_entity_access';

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
   *
   * @param \Drupal\Core\Database\Connection $database
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    Connection $database,
    EntityTypeManagerInterface $entityTypeManager
  ) {
    $this->database = $database;
    $this->userStorage = $entityTypeManager->getStorage('user');
  }

  /**
   * @inheritDoc
   */
  public function onSave(Model $model): void {
    if (!$this->dependenciesMet($model)) {
      return;
    }

    $entityType = $model::entityType();
    $entityId = $model->getId();

    // Delete all current permissions.
    $this->database->delete(self::TABLE_ENTITY_ACCESS)
      ->condition('entity_type', $entityType)
      ->condition('entity_id', $entityId)
      ->execute();

    // Create an insert query. This query will be used to insert all
    // permissions at once.
    $insertQuery = $this->database->insert(self::TABLE_ENTITY_ACCESS);
    $insertQuery->fields(['entity_type', 'entity_id', 'uid']);

    foreach ($this->getUserIds($model) as $uid) {
      $insertQuery->values([
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'uid' => $uid,
      ]);
    }

    $insertQuery->execute();
  }

  /**
   * @inheritDoc
   */
  public function onQuery(AlterableInterface $query): AlterableInterface {
    // TODO: Implement onQuery() method.
    return $query;
  }

  /**
   * @param \Drupal\spectrum\Model\Model $model
   *
   * @return bool
   */
  protected function dependenciesMet(Model $model): bool {
    $requiredRelationships = ['company', 'contact', 'organization'];
    $foundRelationships = [];

    foreach ($model::relationships() as $relationship) {
      $name = $relationship->getName();

      if (in_array($name, $requiredRelationships)) {
        $foundRelationships[] = $name;
      }
    }

    return sizeof($requiredRelationships) === sizeof($foundRelationships);
  }

  /**
   * @param \Drupal\spectrum\Model\Model $model
   *
   * @return array
   */
  protected function getUserIds(Model $model): array {
    $users = [];

    // If there is a contact related to the model, and the contact is related
    // to a user, insert permissions for that user.
    /** @var \Drupal\mist_crm\Models\Object\Contact $contact */
    if ($contact = $model->fetch('contact')) {
      /** @var \Drupal\mist_crm\Models\User $user */
      if ($user = $contact->fetch('user')) {
        $users[] = $user->getId();
      }
    }

    /** @var \Drupal\mist_crm\Models\Object\Company $company */
    if ($company = $model->fetch('company')) {
      $company->fetch('contacts');
      $userIds = $company->fetch('contacts.users')->getIds();
      $users = array_merge($users, $userIds);
    }

    /** @var \Drupal\mist_crm\Models\Organization\Organization $organization */
    if ($organization = $model->fetch('organization')) {
      // @todo: there's no mapping between user and organization yet.
      // Give access to all atlas users for now.
      $result = $this->userStorage->getQuery()
        ->condition('status', 1)
        ->condition('roles', 'atlas_user')
        ->execute();
      $users = array_merge($users, $result);
    }

    return array_unique($users);
  }

}
