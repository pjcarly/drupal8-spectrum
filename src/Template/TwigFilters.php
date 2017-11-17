<?php
namespace Drupal\spectrum\Template;

use Drupal\spectrum\Models\Image;
use Drupal\spectrum\Models\File;
use Drupal\spectrum\Utils\DateUtils;

use CommerceGuys\Addressing\Formatter\PostalLabelFormatter;

class TwigFilters extends \Twig_Extension
{
  /**
   * Generates a list of all Twig filters that this extension defines.
   */
  public function getFilters()
  {
    return [
      new \Twig_SimpleFilter('src', array($this, 'src')),
      new \Twig_SimpleFilter('file_src', array($this, 'fileSrc')),
      new \Twig_SimpleFilter('base64src', array($this, 'base64src')),
      new \Twig_SimpleFilter('address', array($this, 'address')),
      new \Twig_SimpleFilter('price', array($this, 'price')),
      new \Twig_SimpleFilter('autonumber_format', array($this, 'autonumberFormat')),
      new \Twig_SimpleFilter('pad_left', array($this, 'padLeft')),
    ];
  }

  /**
   * Gets a unique identifier for this Twig extension.
   */
  public function getName()
  {
    return 'spectrum.twigfilters';
  }

  /**
   * Get the image SRC with possible image style
   */
  public static function src(Image $image, string $imagestyle = null)
  {
    return $image->getSRC($imagestyle);
  }

  public static function fileSrc(File $file)
  {
    return $file->getSRC();
  }

  /**
   * Get the base 64 image SRC with possible image style
   */
  public static function base64src(Image $image, string $imagestyle = null)
  {
    return $image->getBase64SRC($imagestyle);
  }

  public static function address($address)
  {
    $container = \Drupal::getContainer();
    $formatter = new PostalLabelFormatter($container->get('address.address_format_repository'), $container->get('address.country_repository'), $container->get('address.subdivision_repository'), 'EN', 'en');

    return $formatter->format($address);
  }

  public static function price($price, $currency = null)
  {
    return number_format((float) $price, 2, ',', ' ') . ' ' . $currency;
  }

  public static function padLeft($value, $length, $padvalue = '0')
  {
    return str_pad($value, $length, $padvalue, STR_PAD_LEFT);
  }

  public static function autonumberFormat($value, $format, $date)
  {
    $formatted = DateUtils::generatePatternString($date, $format);
    $formatted =  str_replace('{{VALUE}}', $value, $formatted);
    return $formatted;
  }
}
