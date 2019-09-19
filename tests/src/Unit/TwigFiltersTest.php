<?php

namespace Drupal\Tests\spectrum\Unit;

use Drupal\spectrum\Template\TwigFilters;
use Money\Currency;
use Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * Class TwigFiltersTest
 *
 * @package Drupal\Tests\spectrum\Unit
 */
class TwigFiltersTest extends TestCase {

  /**
   * @return void
   */
  public function testPrice() {
    $money = new Money('5000', new Currency('EUR'));
    $this->assertEquals('50,00 EUR', TwigFilters::price($money));
    $this->assertEquals('50,00 EUR', TwigFilters::price('50', 'EUR'));
    $this->assertEquals('50,00 ', TwigFilters::price('50'));
    $this->assertEquals('50,00 EUR', TwigFilters::price(50, 'EUR'));
    $this->assertEquals('50,00 EUR', TwigFilters::price(50.0, 'EUR'));
  }

}
