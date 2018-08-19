<?php

namespace Drupal\spectrum\Utils;

use Drupal\Core\Field\FieldItemList;
use CommerceGuys\Addressing\Address;
use CommerceGuys\Addressing\Formatter\PostalLabelFormatter;

class AddressUtils
{
  /**
   * Converts a Drupal FieldItemList (address field) to a CommerceGuys address object
   *
   * @param FieldItemList $addressField
   * @return Address|null
   */
  public static function getAddress(FieldItemList $addressField) : ?Address
  {
    $address = null;

    if(!empty($addressField->country_code))
    {
      $address = new Address($addressField->country_code,
      $addressField->administrative_area,
      $addressField->locality,
      $addressField->dependent_locality,
      $addressField->postal_code,
      $addressField->sorting_code,
      $addressField->address_line1,
      $addressField->address_line2);
    }

    return $address;
  }

  /**
   * Formats an address field according to the country's address formatting
   *
   * @param FieldItemList $addressField
   * @return string|null
   */
  public static function formatField(FieldItemList $addressField) : ?string
  {
    $address = static::getAddress($addressField);
    return static::format($address);
  }

  /**
   * Format an address object according to the country's address formatting
   *
   * @param Address|null $address
   * @return string|null
   */
  public static function format(?Address $address) : ?string
  {
    if(empty($address))
    {
      return '';
    }

    $container = \Drupal::getContainer();
    $formatter = new PostalLabelFormatter($container->get('address.address_format_repository'), $container->get('address.country_repository'), $container->get('address.subdivision_repository'));

    return $formatter->format($address, ['origin_country' => 'BE']);
  }
}
