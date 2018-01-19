<?php

namespace Drupal\spectrum\Utils;

use Stringy\StaticStringy as Stringy;
use Drupal\Component\Transliteration\PhpTransliteration;

class StringUtils
{
  public static function pnrSafeString(string $input) : string
  {
    return static::upperCase(static::keepAlphaCharactersAndSpaces(static::transliterate($input)));
  }

  public static function upperCase(string $input) : string
  {
    return strtoupper($input);
  }

  public static function keepAlphaCharactersAndSpaces(string $input) : string
  {
    return preg_replace("/[^A-Za-z ]/", '', $input);
  }

  public static function transliterate(string $input) : string
  {
    $transliterator = new PhpTransliteration();
    $response = $transliterator->transliterate($input);
    return $response;
  }

  public static function camelize(string $input) : string
  {
    return Stringy::camelize($input);
  }

  public static function dasherize(string $input) : string
  {
    return Stringy::dasherize($input);
  }

  public static function underscore(string $input) : string
  {
    return Stringy::underscored($input);
  }

  public static function hasSpaces(string $string) : bool
  {
    return preg_match('/\s/', $string);
  }

  public static function contains(string $haystack, string $needle) : bool
  {
    return strpos($haystack, $needle) !== false;
  }

  public static function isAlphaNumericWithoutSpaces(string $string) : bool
  {
    return preg_match('/^[a-z0-9 .\-]+$/i', $string);
  }

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
