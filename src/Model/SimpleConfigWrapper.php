<?php

namespace Drupal\spectrum\Model;

use Drupal\Core\Config\ImmutableConfig;

/**
 * This class exposes magic getters to get values from a model without having to know the drupal implementation
 * Useful for within Email templates for example, where we can just get {{ account.name }} instead of {{ account.entity.title.value }}
 */
class SimpleConfigWrapper
{
  private $config;

  /**
   * @param ImmutableConfig $config The drupal config you want to wrap
   */
  public function __construct(ImmutableConfig $config)
  {
    $this->config = $config->get();
  }

  public function __get($property)
  {
    $config = $this->config;
    return $config[$property];
  }

  public function __isset($property)
  {
    $config = $this->config;
    return array_key_exists($property, $config);
  }
}
