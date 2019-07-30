<?php

namespace Drupal\spectrum\Utils;

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
    $request = \Drupal::request();
    $rootUrl = $request->getSchemeAndHttpHost() . base_path();
    $config = \Drupal::config('spectrum.settings');

    if ($rootUrl === 'http://default/') // Executed from CLI
    {
      $rootUrl = $config->get('default_base_path');
    }

    return $rootUrl;
  }
}
