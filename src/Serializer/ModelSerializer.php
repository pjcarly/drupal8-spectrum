<?php

namespace Drupal\spectrum\Serializer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Psr\Log\LoggerInterface;

class ModelSerializer
{
  protected LoggerInterface $logger;
  protected EntityFieldManagerInterface $fieldManager;

  public function __construct(LoggerInterface $logger, EntityFieldManagerInterface $fieldManager)
  {
    $this->logger = $logger;
    $this->fieldManager = $fieldManager;
  }

  /**
   * Returns a array of defaults fields on a drupal entity that should be ignored in serialization
   *
   * @return string[]
   */
  public function getDefaultIgnoreFields(): array
  {
    return [
      'type',
      'revision_log',
      'vid',
      'revision_timestamp',
      'revision_uid',
      'revision_log',
      'revision_translation_affected',
      'revision_translation_affected',
      'revision_default',
      'revision_id',
      'revision_created',
      'path',
      'default_langcode',
      'content_translation_source',
      'content_translation_outdated',
      'content_translation_created',
      'content_translation_changed',
      'content_translation_uid',
      'content_translation_status',
      'preferred_langcode',
      'preferred_admin_langcode',
      'langcode',
      'pass',
      'uuid',
      'metatag',
      'field_meta_tags',
      'menu_link',
      'roles',
      'reusable',
      'rh_action',
      'rh_redirect',
      'rh_redirect_response',
      'behavior_settings'
    ];
  }
}
