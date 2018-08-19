<?php

namespace Drupal\spectrum\Utils;

/**
 * Helper methods for language specific functions
 */
class LanguageUtils
{
  /**
   * The language that you want to translate into, in case this value is null, the users's language will be used
   *
   * @var string
   */
  private static $forcedLanguage;

  /**
   * Sets a global variable to a certain language, all calls to the LanguageUtils::translate function will use the forced language
   * This can be used to get translations in another language than the user's language
   *
   * @param string $language
   * @return void
   */
  public static function setForcedLanguage(string $language)
  {
    static::$forcedLanguage = $language;
  }

    /**
   * Translate the passed in value through the Drupal t function, extra varialbes can be passed
   *
   * @param string $value
   * @param array $variables
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public static function translate(string $value, array $variables = [])
  {
    $language = static::$forcedLanguage;

    if(empty($language))
    {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    }

    return t($value, $variables,  ['langcode' => $language]);
  }
}
