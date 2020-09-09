<?php

namespace Drupal\spectrum\Exceptions;

use Drupal\spectrum\Serializer\JsonApiErrorParsableInterface;

class ModelNotFoundException extends \Exception implements JsonApiErrorParsableInterface
{
  public function getStatus(): ?string
  {
    return '404';
  }

  public function getDetail(): ?string
  {
    return 'Model not found';
  }

  public function getPointer(): ?string
  {
    return '/data';
  }

  public function getTitle(): ?string
  {
    return $this->getDetail();
  }

  public function getErrorCode(): ?string
  {
    return 'model_not_found';
  }
}
