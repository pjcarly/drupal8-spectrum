<?php

namespace Drupal\spectrum\Utils;

use CommerceGuys\Addressing\AddressInterface;
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
  public static function getAddress(FieldItemList $addressField): ?Address
  {
    $address = null;

    if (!empty($addressField->country_code)) {
      $address = new Address(
        $addressField->country_code,
        $addressField->administrative_area,
        $addressField->locality,
        $addressField->dependent_locality,
        $addressField->postal_code,
        $addressField->sorting_code,
        $addressField->address_line1,
        $addressField->address_line2
      );
    }

    return $address;
  }

  /**
   * Formats an address field according to the country's address formatting
   *
   * @param FieldItemList $addressField
   * @return string|null
   */
  public static function formatField(FieldItemList $addressField): ?string
  {
    $address = static::getAddress($addressField);
    return static::format($address);
  }

  /**
   * Format an address object according to the country's address formatting
   *
   * @param Address|null $address
   * @param string $address
   * @return string|null
   */
  public static function format(?Address $address, ?string $originCountry = null): ?string
  {
    if (empty($address)) {
      return '';
    }

    $container = \Drupal::getContainer();
    $formatter = new PostalLabelFormatter($container->get('address.address_format_repository'), $container->get('address.country_repository'), $container->get('address.subdivision_repository'));

    return $formatter->format($address, ['origin_country' => $originCountry ?? 'BE']);
  }

  /**
   * @param AddressInterface $address
   * @return bool
   */
  public static function isInEU(AddressInterface $address): bool
  {
    return in_array($address->getCountryCode(), ['AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE',
      'ES', 'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU',
      'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK']);
  }
}
