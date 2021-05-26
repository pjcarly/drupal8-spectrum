<?php

namespace Drupal\spectrum\Services;

use Drupal\field\FieldConfigInterface;
use Drupal\spectrum\Models\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface FileServiceInterface
{
  /**
   * Gets the target field for an upload from a Request
   *
   * @param Request $request
   * @return string|null
   */
  public function getTargetFromRequest(Request $request): ?string;

  /**
   * Create a new FileModel by saving a data blob, getting the entity from drupal and wrapping it in a model
   *
   * @param string $uriScheme
   * @param string $directory
   * @param string $filename
   * @param mixed $data the blob of the file you want to save
   * @return File
   */
  public function createNewFile(string $uriScheme, string $directory, string $filename, $data): File;

  /**
   * @param string|null $target
   * @return Response
   */
  public function handleUploadForTarget(?string $target): Response;

  /**
   * @param string $target
   * @return FieldConfigInterface|null
   */
  public function getFieldConfigForFieldTarget(string $target): ?FieldConfigInterface;
}
