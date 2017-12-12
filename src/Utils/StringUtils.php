<?php

namespace Drupal\spectrum\Utils;

use Stringy\StaticStringy as Stringy;

class StringUtils
{
  static function camelize($input)
  {
    return Stringy::camelize($input);
  }

  static function dasherize($input)
  {
    return Stringy::dasherize($input);
  }

  static function underscored($input)
  {
    return Stringy::dasherize($input);
  }

  static function hasSpaces($string)
  {
    return preg_match('/\s/', $string);
  }

  static function contains($haystack, $needle)
  {
    return strpos($haystack, $needle) !== false;
  }

  static function isAlphaNumericWithoutSpaces($string)
  {
    return preg_match('/^[a-z0-9 .\-]+$/i', $string);
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
  static function positionsOfSubstring($haystack, $needle)
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
