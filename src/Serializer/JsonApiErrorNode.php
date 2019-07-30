<?php

namespace Drupal\spectrum\Serializer;

/**
 * A JsonApiErrorNode is an instance of a single error response
 */
class JsonApiErrorNode
{
  /**
   * The status code fot htis error
   *
   * @var string|null
   */
  protected $status;

  /**
   * The pointer to the jsonapi origin location that caused the error
   *
   * @var string
   */
  protected $pointer;

  /**
   * Application specific error code
   *
   * @var string|null
   */
  protected $code;

  /**
   * Title of the error
   *
   * @var string|null
   */
  protected $title;

  /**
   * Detail information of this error
   *
   * @var string|null
   */
  protected $detail;

  /**
   * Returns a serialized stdclass of this instance
   *
   * @return \stdClass
   */
  public function serialize(): \stdClass
  {
    $serialized = new \stdClass();

    if (isset($this->status)) {
      $serialized->status = $this->status;
    }

    if (isset($this->pointer)) {
      $serialized->source = new \stdClass();
      $serialized->source->pointer = $this->pointer;
    }

    if (isset($this->code)) {
      $serialized->code = $this->code;
    }

    if (isset($this->title)) {
      $serialized->title = $this->title;
    }

    if (isset($this->detail)) {
      $serialized->detail = $this->detail;
    }

    return $serialized;
  }

  /**
   * Get the status code fot htis error
   *
   * @return  string|null
   */
  public function getStatus(): ?string
  {
    return $this->status;
  }

  /**
   * Set the status code fot htis error
   *
   * @param  string|null  $status  The status code fot htis error
   *
   * @return  self
   */
  public function setStatus($status): JsonApiErrorNode
  {
    $this->status = $status;

    return $this;
  }

  /**
   * Get application specific error code
   *
   * @return  string|null
   */
  public function getCode(): ?string
  {
    return $this->code;
  }

  /**
   * Set application specific error code
   *
   * @param  string|null  $code  Application specific error code
   *
   * @return  self
   */
  public function setCode($code): JsonApiErrorNode
  {
    $this->code = $code;

    return $this;
  }

  /**
   * Get title of the error
   *
   * @return  string|null
   */
  public function getTitle(): ?string
  {
    return $this->title;
  }

  /**
   * Set title of the error
   *
   * @param  string|null  $title  Title of the error
   *
   * @return  self
   */
  public function setTitle($title): JsonApiErrorNode
  {
    $this->title = $title;

    return $this;
  }

  /**
   * Get detail information of this error
   *
   * @return  string|null
   */
  public function getDetail(): ?string
  {
    return $this->detail;
  }

  /**
   * Set detail information of this error
   *
   * @param  string|null  $detail  Detail information of this error
   *
   * @return  self
   */
  public function setDetail($detail): JsonApiErrorNode
  {
    $this->detail = $detail;

    return $this;
  }

  /**
   * Get the pointer to the jsonapi origin location that caused the error
   *
   * @return  string
   */
  public function getPointer(): ?string
  {
    return $this->pointer;
  }

  /**
   * Set the pointer to the jsonapi origin location that caused the error
   *
   * @param  string  $pointer  The pointer to the jsonapi origin location that caused the error
   *
   * @return  self
   */
  public function setPointer(string $pointer): JsonApiErrorNode
  {
    $this->pointer = $pointer;

    return $this;
  }
}
