<?php

namespace Drupal\spectrum\Serializer;

interface JsonApiErrorParsableInterface
{
  public function getDetail(): ?string;
  public function getPointer(): ?string;
  public function getStatus(): ?string;
  public function getErrorCode(): ?string;
  public function getTitle(): ?string;
}
