<?php

namespace Drupal\spectrum\Utils;

class UrlUtils
{
  public static function getBaseURL()
  {
    $request = \Drupal::request();
    $rootUrl = $request->getSchemeAndHttpHost() . base_path();
    $config = \Drupal::config('spectrum.settings');

    if($rootUrl === 'http://default/') // Executed from CLI
    {
       $rootUrl = $config->get('default_base_path');
    }

    return $rootUrl;
  }
}
