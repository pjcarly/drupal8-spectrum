<?php

namespace Drupal\spectrum\Utils;

use Money\Money;
use Money\Currencies\ISOCurrencies;
use Money\Parser\DecimalMoneyParser;
use Money\Formatter\DecimalMoneyFormatter;

/**
 * This class provides Url helper functions used throughout the application
 */
class NumberUtils
{
  /**
   * Returns a Money value for the passed number string, and currency
   *
   * @param string $value
   * @param string $currency
   * @return Money
   */
  public static function getMoney(string $value, string $currency) : Money
  {
    $currencies = new ISOCurrencies();
    $moneyParser = new DecimalMoneyParser($currencies);

    return $moneyParser->parse($value, $currency);
  }

  /**
   * Returns a Decimal (float) value of the passed Money object, if the passed in money object is null, then 0 will be returned
   *
   * @param Money $money
   * @return float
   */
  public static function getDecimal(?Money $money) : float
  {
    if(empty($money))
    {
      return 0;
    }

    $currencies = new ISOCurrencies();
    $moneyFormatter = new DecimalMoneyFormatter($currencies);

    return $moneyFormatter->format($money);
  }
}
