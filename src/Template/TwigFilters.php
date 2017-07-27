<?php
namespace Drupal\spectrum\Template;

use Drupal\spectrum\Models\Image;

class TwigFilters extends \Twig_Extension
{
  /**
   * Generates a list of all Twig filters that this extension defines.
   */
  public function getFilters()
  {
    return [
      new \Twig_SimpleFilter('src', array($this, 'src')),
      new \Twig_SimpleFilter('base64src', array($this, 'base64src')),
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

  /**
   * Get the base 64 image SRC with possible image style
   */
  public static function base64src(Image $image, string $imagestyle = null)
  {
    return $image->getBase64SRC($imagestyle);
  }
}
