<?php

namespace Drupal\spectrum\Utils;

use \DateTime;
use \DateTimeZone;

class DateUtils
{
  // $datestring: must be in format MM-DDThh:mm
  // returns DateTime
  public static function getNextUTCDateTimeForMMDDHourString(string $datestring, string $timezone = '') : DateTime
  {
    $dateSplitted = explode('T', $datestring);
    $time = $dateSplitted[1];
    $dateMonthAndDay = explode('-', $dateSplitted[0]);
    $day = $dateMonthAndDay[1];
    $month = $dateMonthAndDay[0];
    $fullDateString = static::getNextDateStringForDayAndMonth($day, $month);

    $date = new DateTime($fullDateString.'T'.$time.(empty($timezone) ? '' : '+'.$timezone));
    $date->setTimezone(new DateTimeZone('UTC'));
    return $date;
  }


  // PNR dates are never more than 1 year in the future
  // Hear we calculate a year, if today is 5 MAY, and we make a booking for 6 MAY, the booking is this year, if we make a booking for 4 MAY it is next year
  public static function getNextDateStringForDayAndMonth(int $day, int $month) : string
  {
    $now = new DateTime();
    $year = $now->format('Y');

    if($month < $now->format('m') || $month == $now->format('m') && $day < $now->format('d'))
    {
      $year++;
    }

    return $year.'-'.str_pad($month, 2, '0', STR_PAD_LEFT).'-'.str_pad($day, 2, '0', STR_PAD_LEFT);
  }

  public static function getHourMinuteStringForGmtOffset(float $gmtOffset) : string
  {
    $returnValue = sprintf('%02d:%02d', (int) $gmtOffset, fmod($gmtOffset, 1) * 60);
    return $returnValue;
  }

  public static function getToday() : int
  {
    return strtotime('today midnight');
  }

  public static function generatePatternString(DateTime $date, string $pattern) : string
  {
    $value = $pattern;
    $value = str_replace('{{YYYY}}', $date->format('y'), $value);
    $value = str_replace('{{YY}}', str_pad($date->format('Y'), 2, '0', STR_PAD_LEFT), $value);
    $value = str_replace('{{QQ}}', str_pad(static::getQuarter($date), 2, '0', STR_PAD_LEFT), $value);
    $value = str_replace('{{MM}}', str_pad($date->format('n'), 2, '0', STR_PAD_LEFT), $value);
    $value = str_replace('{{DD}}', str_pad($date->format('d'), 2, '0', STR_PAD_LEFT), $value);
    return $value;
  }

  public static function getQuarter(DateTime $date) : float
  {
    return ceil($date->format('n')/3);
  }

  public static function getMonthNumber(string $month) : int
  {
    $monthNumber = false;
    $month = strtoupper($month);

    if($month === 'JAN')
    {
      $monthNumber = 1;
    }
    else if($month === 'FEB')
    {
      $monthNumber = 2;
    }
    else if($month === 'MAR')
    {
      $monthNumber = 3;
    }
    else if($month === 'APR')
    {
      $monthNumber = 4;
    }
    else if($month === 'MAY')
    {
      $monthNumber = 5;
    }
    else if($month === 'JUN')
    {
      $monthNumber = 6;
    }
    else if($month === 'JUL')
    {
      $monthNumber = 7;
    }
    else if($month === 'AUG')
    {
      $monthNumber = 8;
    }
    else if($month === 'SEP')
    {
      $monthNumber = 9;
    }
    else if($month === 'OCT')
    {
      $monthNumber = 10;
    }
    else if($month === 'NOV')
    {
      $monthNumber = 11;
    }
    else if($month === 'DEC')
    {
      $monthNumber = 12;
    }

    return $monthNumber;
  }
}
