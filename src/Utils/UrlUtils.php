<?php

namespace Drupal\spectrum\Utils;

use Drupal\Core\Site\Settings;

/**
 * This class provides Url helper functions used throughout the application
 */
class UrlUtils
{
  /**
   * Returns the Base URL where you can add a dynamic part to later on
   *
   * @return string
   */
  public static function getBaseURL(): string
  {
    return Settings::get('base_url');
  }
}
