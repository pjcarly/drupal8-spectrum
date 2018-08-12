<?php

namespace Drupal\spectrum\Serializer;

/**
 * A JsonApiLink is a class that makes it possible to add links to the jsonapi structure, with either params or no params
 */
class JsonApiLink
{
  /**
   * the Key of the link
   *
   * @var string
   */
  protected $name;

  /**
   * The query params you want to add to the link
   *
   * @var string
   */
  protected $params;

  /**
   * The base url, where the params will be added to
   *
   * @var string
   */
  protected $baseUrl;

  /**
   * @param string $name The key that will be used to serialize this link into
   * @param string $baseUrl The URL that will be the value of the key
   */
  public function __construct(string $name, string $baseUrl)
  {
    $this->name = $name;
    $this->baseUrl = $baseUrl;
    $this->params = [];
  }

  /**
   * Adds a query parameter to this link
   *
   * @param string $name the key of the query param: https://baseurl?name=value
   * @param string|int $value The value of the query param: https://baseurl?name=value
   * @return JsonApiLink
   */
  public function addParam(string $name, $value) : JsonApiLink
  {
    $this->params[$name] = $value;
    return $this;
  }

  /**
   * Returns a string with the base url, and possible query params applied in the query string
   *
   * @return string
   */
  public function getUrl() : string
  {
    return empty($this->params) ? $this->baseUrl : $this->baseUrl . '?' . http_build_query($this->params);
  }
}
