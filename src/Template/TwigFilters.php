<?php
namespace Drupal\spectrum\Template;

use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Model\SimpleCollectionWrapper;
use Drupal\spectrum\Models\Image;
use Drupal\spectrum\Models\File;
use Drupal\spectrum\Utils\DateUtils;
use Drupal\spectrum\Utils\StringUtils;
use Drupal\spectrum\Utils\LanguageUtils;

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
      new \Twig_SimpleFilter('address_format', array($this, 'addressFormat')),
      new \Twig_SimpleFilter('price', array($this, 'price')),
      new \Twig_SimpleFilter('autonumber_format', array($this, 'autonumberFormat')),
      new \Twig_SimpleFilter('pad_left', array($this, 'padLeft')),
      new \Twig_SimpleFilter('pnr_safe_name', array($this, 'pnrSafeName')),
      new \Twig_SimpleFilter('target_id', array($this, 'targetId')),
      new \Twig_SimpleFilter('collection_sort', array($this, 'collectionSort')),
      new \Twig_SimpleFilter('tt', array($this, 'translate'))
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

  public static function addressFormat($address)
  {
    if(empty($address))
    {
      return '';
    }

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

  public static function pnrSafeName($value)
  {
    return StringUtils::pnrSafeString($value);
  }

  public static function targetId(SimpleModelWrapper $simpleModel, string $field)
  {
    return $simpleModel->getModel()->entity->$field->target_id;
  }

  public static function collectionSort(SimpleCollectionWrapper $simpleCollection, string $sortingFunction)
  {
    $collection = $simpleCollection->getCollection();
    $collection->sort($sortingFunction);

    return $simpleCollection;
  }

  public static function translate($value, $variables = [])
  {
    return LanguageUtils::translate($value, $variables);
  }
}
