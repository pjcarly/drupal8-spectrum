<?php

namespace Drupal\spectrum\Utils;

use Stringy\StaticStringy as Stringy;

class StringUtils
{
  static function camelize(string $input) : string
  {
    return Stringy::camelize($input);
  }

  static function dasherize(string $input) : string
  {
    return Stringy::dasherize($input);
  }

  static function underscore(string $input) : string
  {
    return Stringy::underscored($input);
  }

  static function hasSpaces(string $string) : bool
  {
    return preg_match('/\s/', $string);
  }

  static function contains(string $haystack, string $needle) : bool
  {
    return strpos($haystack, $needle) !== false;
  }

  static function isAlphaNumericWithoutSpaces(string $string) : bool
  {
    return preg_match('/^[a-z0-9 .\-]+$/i', $string);
  }

  static function generateUUID() : string
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
  static function positionsOfSubstring(string $haystack, string $needle)
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
