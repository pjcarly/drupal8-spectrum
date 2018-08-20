<?php

namespace Drupal\spectrum\Utils;

use Stringy\StaticStringy as Stringy;
use Drupal\Component\Transliteration\PhpTransliteration;

/**
 * This class provides String Helper functions
 */
class StringUtils
{
  /**
   * Returns a PNR safe string version of the input string
   *
   * @param string $input
   * @return string
   */
  public static function pnrSafeString(string $input) : string
  {
    return static::upperCase(static::keepAlphaCharactersAndSpaces(static::transliterate($input)));
  }

  /**
   * Uppercases the input string
   *
   * @param string $input
   * @return string
   */
  public static function upperCase(string $input) : string
  {
    return strtoupper($input);
  }

  /**
   * Keep alpha characters and spaces
   *
   * @param string $input
   * @return string
   */
  public static function keepAlphaCharactersAndSpaces(string $input) : string
  {
    return preg_replace('/[^A-Za-z ]/', '', $input);
  }

  /**
   * Transliterates the input string, all special characters will be replaces with their alpha equivalent for example élève becomes eleve
   *
   * @param string $input
   * @return string
   */
  public static function transliterate(string $input) : string
  {
    $transliterator = new PhpTransliteration();
    $response = $transliterator->transliterate($input);
    return $response;
  }

  /**
   * Camelizes the input string getflights becomes Getflights
   *
   * @param string $input
   * @return string
   */
  public static function camelize(string $input) : string
  {
    return Stringy::camelize($input);
  }

  /**
   * Dasherizes the input string "get_flights and groupflights" becomes "get-flights-and-groupflights"
   *
   * @param string $input
   * @return string
   */
  public static function dasherize(string $input) : string
  {
    return Stringy::dasherize($input);
  }

  /**
   * Underscores the input string "get_flights and groupflights" becomes "get_flights_and_groupflights"
   *
   * @param string $input
   * @return string
   */
  public static function underscore(string $input) : string
  {
    return Stringy::underscored($input);
  }

  /**
   * Returns true if the input string contains spaces
   *
   * @param string $string
   * @return boolean
   */
  public static function hasSpaces(string $string) : bool
  {
    return preg_match('/\s/', $string);
  }

  /**
   * Checks whether the haystack string contains the needle string
   *
   * @param string $haystack
   * @param string $needle
   * @return boolean
   */
  public static function contains(string $haystack, string $needle) : bool
  {
    return strpos($haystack, $needle) !== false;
  }

  /**
   * Returns true if the input string contains a number
   *
   * @param string $text
   * @return boolean
   */
  public static function hasNumber(string $text) : bool
  {
    return preg_match('/\d/', $text) > 0;
  }

  /**
   * Returns true if the input string consists only of alpha numeric characters (no spaces)
   *
   * @param string $string
   * @return boolean
   */
  public static function isAlphaNumericWithoutSpaces(string $string) : bool
  {
    return preg_match('/^[a-z0-9 .\-]+$/i', $string);
  }

  /**
   * Generate a UUID
   *
   * @return string
   */
  public static function generateUUID() : string
  {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
        mt_rand( 0, 0xffff ),
        mt_rand( 0, 0x0fff ) | 0x4000,
        mt_rand( 0, 0x3fff ) | 0x8000,
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
  }

  /**
   * mb_stripos all occurences
   * based on http://www.php.net/manual/en/function.strpos.php#87061
   *
   * Find all occurrences of a needle in a haystack (case-insensitive, UTF8)
   *
   * @param string $haystack
   * @param string $needle
   * @return array or false
   */
  public static function positionsOfSubstring(string $haystack, string $needle)
  {
    $s = 0;
    $i = 0;

    while(is_integer($i))
    {
      $i = mb_stripos($haystack, $needle, $s);

      if(is_integer($i))
      {
        $aStrPos[] = $i;
        $s = $i + mb_strlen($needle);
      }
    }

    if(isset($aStrPos))
    {
      return $aStrPos;
    }
    else
    {
      return false;
    }
  }
}
