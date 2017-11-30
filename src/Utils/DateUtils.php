<?php

namespace Drupal\spectrum\Utils;

class DateUtils
{
  public static function getToday()
  {
    return strtotime('today midnight');
  }

  public static function generatePatternString(\DateTime $date, $pattern)
  {
    $value = $pattern;
    $value = str_replace('{{YYYY}}', $date->format('y'), $value);
    $value = str_replace('{{YY}}', str_pad($date->format('Y'), 2, "0", STR_PAD_LEFT), $value);
    $value = str_replace('{{QQ}}', str_pad(static::getQuarter($date), 2, "0", STR_PAD_LEFT), $value);
    $value = str_replace('{{MM}}', str_pad($date->format('n'), 2, "0", STR_PAD_LEFT), $value);
    $value = str_replace('{{DD}}', str_pad($date->format('d'), 2, "0", STR_PAD_LEFT), $value);
    return $value;
  }

  public static function getQuarter(\DateTime $date)
  {
    return ceil($date->format('n')/3);
  }

  public static function getMonthNumber($month)
  {
    $monthNumber = false;
    $month = strtoupper($month);

    if($month === "JAN")
    {
      $monthNumber = 1;
    }
    else if($month === "FEB")
    {
      $monthNumber = 2;
    }
    else if($month === "MAR")
    {
      $monthNumber = 3;
    }
    else if($month === "APR")
    {
      $monthNumber = 4;
    }
    else if($month === "MAY")
    {
      $monthNumber = 5;
    }
    else if($month === "JUN")
    {
      $monthNumber = 6;
    }
    else if($month === "JUL")
    {
      $monthNumber = 7;
    }
    else if($month === "AUG")
    {
      $monthNumber = 8;
    }
    else if($month === "SEP")
    {
      $monthNumber = 9;
    }
    else if($month === "OCT")
    {
      $monthNumber = 10;
    }
    else if($month === "NOV")
    {
      $monthNumber = 11;
    }
    else if($month === "DEC")
    {
      $monthNumber = 12;
    }

    return $monthNumber;
  }
}
