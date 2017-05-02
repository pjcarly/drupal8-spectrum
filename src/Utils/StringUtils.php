<?php

namespace Drupal\spectrum\Utils;

class StringUtils
{
  static function camelize($input, $separator = '_')
  {
    return str_replace($separator, '', lcfirst(ucwords($input, $separator)));
  }

  static function dasherize($input, $separator = '_')
  {
    return strtr($input, $separator, '-');
  }

  static function underscore($input, $separator = '-')
  {
    return strtr($input, $separator, '_');
  }

  static function hasSpaces($string)
  {
    return preg_match('/\s/', $string);
  }
  
  static function isAlphaNumericWithoutSpaces($string)
  {
    return preg_match('/^[a-z0-9 .\-]+$/i', $string);
  }
}
