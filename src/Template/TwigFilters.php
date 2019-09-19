<?php

namespace Drupal\spectrum\Template;

use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Model\SimpleCollectionWrapper;
use Drupal\spectrum\Models\Image;
use Drupal\spectrum\Models\File;
use Drupal\spectrum\Utils\DateUtils;
use Drupal\spectrum\Utils\NumberUtils;
use Drupal\spectrum\Utils\StringUtils;
use Drupal\spectrum\Utils\LanguageUtils;
use Drupal\spectrum\Utils\AddressUtils;
use CommerceGuys\Addressing\Address;
use Money\Currencies\CurrencyList;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;

/**
 * THis class contains a whole list of Twig Filters to make it easier to use model functionality in twig renders
 */
class TwigFilters extends \Twig_Extension
{
  /**
   * Generates a list of all Twig filters that this extension defines.
   *
   * @return array
   */
  public function getFilters()
  {
    return [
      new \Twig_SimpleFilter('src', [$this, 'src']),
      new \Twig_SimpleFilter('file_src', [$this, 'fileSrc']),
      new \Twig_SimpleFilter('base64src', [$this, 'base64src']),
      new \Twig_SimpleFilter('address_format', [$this, 'addressFormat']),
      new \Twig_SimpleFilter('price', [$this, 'price']),
      new \Twig_SimpleFilter('autonumber_format', [$this, 'autonumberFormat']),
      new \Twig_SimpleFilter('pad_left', [$this, 'padLeft']),
      new \Twig_SimpleFilter('pnr_safe_name', [$this, 'pnrSafeName']),
      new \Twig_SimpleFilter('target_id', [$this, 'targetId']),
      new \Twig_SimpleFilter('collection_sort', [$this, 'collectionSort']),
      new \Twig_SimpleFilter('tt', [$this, 'translate'])
    ];
  }

  /**
   * Gets a unique identifier for this Twig extension.
   * @return string
   */
  public function getName()
  {
    return 'spectrum.twigfilters';
  }

  /**
   * Get the image SRC with possible image style
   *
   * @param Image $image
   * @param string $imagestyle
   * @return string
   */
  public static function src(Image $image, string $imagestyle = null): string
  {
    return $image->getSRC($imagestyle);
  }

  /**
   * Returns the SRC of the passed in File
   *
   * @param File $file
   * @return string
   */
  public static function fileSrc(File $file): string
  {
    return $file->getSRC();
  }


  /**
   * Get the base 64 image SRC with possible image style
   *
   * @param Image $image
   * @param string $imagestyle
   * @return string
   */
  public static function base64src(Image $image, string $imagestyle = null): string
  {
    return $image->getBase64SRC($imagestyle);
  }

  /**
   * Formats an address according to the country of the address formatting policy
   *
   * @param Address|null $address
   * @return string|null
   */
  public static function addressFormat(?Address $address): ?string
  {
    return AddressUtils::format($address);
  }

  /**
   * Returns a formatted price with Currency
   *
   * @param number|string|Money $price
   * @param string $currency
   * @return string
   */
  public static function price($price, string $currency = null): string
  {
    if (!is_a($price, Money::class)) {
      return number_format((float) $price, 2, ',', ' ') . ' ' . $currency;
    }
    else {
      /** @var Money $price */
      return number_format((float) NumberUtils::getDecimal($price), 2, ',', ' ') . ' ' . $price->getCurrency();
    }
  }

  /**
   * Apply str_pad to the provided value
   *
   * @param string $value
   * @param integer $length
   * @param string $padvalue
   * @return string
   */
  public static function padLeft(string $value, int $length, string $padvalue = '0'): string
  {
    return str_pad($value, $length, $padvalue, STR_PAD_LEFT);
  }

  /**
   * Apply an AutoNumber format to the passed in value
   *
   * @param string $value
   * @param string $format
   * @param \DateTime $date The date you want to use for the dynamic part of the autonumber format
   * @return string
   */
  public static function autonumberFormat(string $value, string $format, \DateTime $date): string
  {
    $formatted = DateUtils::generatePatternString($date, $format);
    $formatted =  str_replace('{{VALUE}}', $value, $formatted);
    return $formatted;
  }

  /**
   * Strip the value of all invalid characters so it can be used in a PNR
   *
   * @param string $value
   * @return string
   */
  public static function pnrSafeName(string $value): string
  {
    return StringUtils::pnrSafeString($value);
  }

  /**
   * Returns the target_id for a specific field
   *
   * @param SimpleModelWrapper $simpleModel
   * @param string $field
   * @return int
   */
  public static function targetId(SimpleModelWrapper $simpleModel, string $field)
  {
    return $simpleModel->getModel()->entity->$field->target_id;
  }

  /**
   * Sort the provided collection through a passed in sorting function name (which should be available on the model)
   *
   * @param SimpleCollectionWrapper $simpleCollection
   * @param string $sortingFunction
   * @return SimpleCollectionWrapper
   */
  public static function collectionSort(SimpleCollectionWrapper $simpleCollection, string $sortingFunction): SimpleCollectionWrapper
  {
    $collection = $simpleCollection->getCollection();
    $collection->sort($sortingFunction);

    return $simpleCollection;
  }

  /**
   * Translate the passed in value through the Drupal t function, extra varialbes can be passed
   *
   * @param string $value
   * @param array $variables
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public static function translate(string $value, array $variables = [])
  {
    return LanguageUtils::translate($value, $variables);
  }
}
