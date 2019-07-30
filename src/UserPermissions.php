<?php

/**
 * @file
 * Contains \Drupal\spectrum\TaxonomyViewsIntegratorPermissions.
 */

namespace Drupal\spectrum;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\spectrum\Model\Model;

/**
 * This class provides a hook into the Drupal permissions functionality, to dynamically allocate permissions.
 * However due to a platform bug it is currently unused
 */
class UserPermissions implements ContainerInjectionInterface
{
  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Constructs a TaxonomyViewsIntegratorPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager)
  {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   *
   * @param ContainerInterface $container
   * @return UserPermissions
   */
  public static function create(ContainerInterface $container): UserPermissions
  {
    return new static($container->get('entity.manager'));
  }

  /**
   * Get permissions for Taxonomy Views Integrator.
   *
   * @return array
   *   Permissions array.
   */
  public function permissions(): array
  {
    $permissions = [];

    //Currently not supported, due to bug in Core where currentuser doesnt return custom permissions
    // if(function_exists('get_registered_model_classes'))
    // {
    //   foreach(get_registered_model_classes() as $modelClass)
    //   {
    //     $permissions[$modelClass::getReadPermissionKey()] = ['title' => $modelClass::entityType() . ' - ' . $modelClass::$plural . ' - 1 READ'];
    //     $permissions[$modelClass::getCreatePermissionKey()] = ['title' => $modelClass::entityType() . ' - ' . $modelClass::$plural . ' - 2 CREATE'];
    //     $permissions[$modelClass::getEditPermissionKey()] = ['title' => $modelClass::entityType() . ' - ' . $modelClass::$plural . ' - 3 EDIT'];
    //     $permissions[$modelClass::getDeletePermissionKey()] = ['title' => $modelClass::entityType() . ' - ' . $modelClass::$plural . ' - 4 DELETE'];
    //   }
    // }

    return $permissions;
  }
}
