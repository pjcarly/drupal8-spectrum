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

  static function underscore($input, $separator = '_')
  {
    $strippedInput = preg_replace('/[^\da-z ]/i', '', $input);
    return str_replace(' ', '_', $strippedInput);
  }
}
