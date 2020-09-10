<?php

namespace Drupal\spectrum\Exceptions;

use Drupal\spectrum\Serializer\JsonApiErrorParsableInterface;

class JsonApiException extends \Exception implements JsonApiErrorParsableInterface
{
  protected $title;
  protected $detail;
  protected $status;
  protected $errorCode;
  protected $pointer;

  public function __construct(string $detail, string $pointer = null, string $status = null)
  {
    $this->detail = $detail;
    $this->pointer = $pointer;
    $this->status = $status;
  }

  public function setDetail(?string $value): self
  {
    $this->detail = $value;
    return $this;
  }

  public function setStatus(?string $value): self
  {
    $this->status = $value;
    return $this;
  }

  public function setPointer(?string $value): self
  {
    $this->pointer = $value;
    return $this;
  }

  public function setErrorCode(?string $value): self
  {
    $this->errorCode = $value;
    return $this;
  }

  public function setTitle(?string $value): self
  {
    $this->title = $value;
    return $this;
  }

  public function getDetail(): ?string
  {
    return $this->detail;
  }

  public function getStatus(): ?string
  {
    return $this->status;
  }

  public function getPointer(): ?string
  {
    return $this->pointer;
  }

  public function getErrorCode(): ?string
  {
    return $this->errorCode;
  }

  public function getTitle(): ?string
  {
    return $this->title;
  }
}
