<?php

namespace Drupal\spectrum\Utils;

class DateUtils
{
  static function generatePatternString(\DateTime $date, $pattern)
  {
    $value = $pattern;
    $value = str_replace('{{YYYY}}', $date->format('y'), $value);
    $value = str_replace('{{YY}}', str_pad($date->format('Y'), 2, "0", STR_PAD_LEFT), $value);
    $value = str_replace('{{QQ}}', str_pad(static::getQuarter($date), 2, "0", STR_PAD_LEFT), $value);
    $value = str_replace('{{MM}}', str_pad($date->format('n'), 2, "0", STR_PAD_LEFT), $value);
    $value = str_replace('{{DD}}', str_pad($date->format('d'), 2, "0", STR_PAD_LEFT), $value);
    return $value;
  }

  static function getQuarter(\DateTime $date)
  {
    return ceil($date->format('n')/3);
  }
}
