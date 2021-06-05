<?php

namespace Drupal\spectrum\Serializer;

interface ModelSerializerInterface
{
  /**
   * Returns a array of defaults fields on a drupal entity that should be ignored in serialization
   *
   * @return string[]
   */
  public function getDefaultIgnoreFields(): array;

  /**
   * @param string $modelClass
   * @return array
   */
  public function getPrettyFieldsToFieldsMapping(string $modelClass): array;

  /**
   * @param string $modelClass
   * @return array
   */
  public function getFieldsToPrettyFieldsMapping(string $modelClass): array;

  /**
   * @param string $modelClass
   * @param string $field
   * @return string|null
   */
  public function getFieldForPrettyField(string $modelClass, string $field): ?string;

  /**
   * @param string $modelClass
   * @param string $field
   * @return string|null
   */
  public function getPrettyFieldForField(string $modelClass, string $field): ?string;

  /**
   * @param string $modelClass
   * @param string $field
   * @return boolean
   */
  public function prettyFieldExists(string $modelClass, string $field): bool;
}
