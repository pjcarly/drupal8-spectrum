<?php

namespace Drupal\spectrum\Serializer;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\spectrum\Services\ModelServiceInterface;
use Drupal\spectrum\Utils\StringUtils;
use Psr\Log\LoggerInterface;

class ModelSerializer implements ModelSerializerInterface
{
  protected LoggerInterface $logger;
  protected EntityFieldManagerInterface $fieldManager;
  protected ModelServiceInterface $modelService;
  protected array $prettyFieldsMapping = [];
  protected array $fieldsMapping = [];

  public function __construct(
    LoggerInterface $logger,
    EntityFieldManagerInterface $fieldManager,
    ModelServiceInterface $modelService
  ) {
    $this->logger = $logger;
    $this->fieldManager = $fieldManager;
    $this->modelService = $modelService;
  }

  /**
   * {@inheritdoc}
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

  /**
   * {@inheritdoc}
   */
  public function getPrettyFieldsToFieldsMapping(string $modelClass): array
  {
    if (!array_key_exists($modelClass, $this->prettyFieldsMapping)) {
      $mapping = [];
      $fieldList = $this->modelService->getFieldDefinitions($modelClass);

      foreach ($fieldList as $key => $value) {
        if ($key !== 'title') {
          $fieldNamePretty = trim(trim(StringUtils::dasherize(str_replace('field_', '', $key)), '-'));
        } else {
          $fieldNamePretty = 'name';
        }

        $mapping[$fieldNamePretty] = $key;
      }

      $this->prettyFieldsMapping[$modelClass] = $mapping;
    }

    return $this->prettyFieldsMapping[$modelClass];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldsToPrettyFieldsMapping(string $modelClass): array
  {
    if (!array_key_exists($modelClass, $this->fieldsMapping)) {
      $prettyMapping = $this->getPrettyFieldsToFieldsMapping($modelClass);
      $mapping = array_flip($prettyMapping);

      $this->fieldsMapping[$modelClass] = $mapping;
    }

    return $this->fieldsMapping[$modelClass];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldForPrettyField(string $modelClass, string $field): ?string
  {
    $mapping = $this->getPrettyFieldsToFieldsMapping($modelClass);

    return array_key_exists($field, $mapping) ? $mapping[$field] : null;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrettyFieldForField(string $modelClass, string $field): ?string
  {
    $mapping = $this->getFieldsToPrettyFieldsMapping($modelClass);

    return array_key_exists($field, $mapping) ? $mapping[$field] : null;
  }

  /**
   * {@inheritdoc}
   */
  public function prettyFieldExists(string $modelClass, string $field): bool
  {
    return !empty($this->getFieldForPrettyField($modelClass, $field));
  }
}
