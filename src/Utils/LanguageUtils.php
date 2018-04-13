<?php

namespace Drupal\spectrum\Utils;

class LanguageUtils
{
  private static $forcedLanguage;

  public static function setForcedLanguage(string $language)
  {
    static::$forcedLanguage = $language;
  }

  public static function translate(string $value, $variables = [])
  {
    $language = static::$forcedLanguage;

    if(empty($language))
    {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    return t($value, $variables,  ['langcode' => $language]);
  }
}
