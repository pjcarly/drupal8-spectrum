<?php

namespace Drupal\spectrum\Models;

use Drupal\image\Entity\ImageStyle;

/**
 * An image model for the Image model, extends from the File Model, with extra functionality for image styles
 */
class Image extends File
{
  /**
   * The Relationships to other models
   *
   * @return void
   */
  public static function relationships()
  {
  }

  /**
   * This function can be used in dynamic api handlers
   *
   * @return string
   */
  protected function getBaseApiPath(): string
  {
    return 'image';
  }

  /**
   * {@inheritdoc}
   *
   * @param string $style (optional) the drupal image style you want to apply
   * @return string
   */
  public function getBase64SRC(string $style = NULL): string
  {
    $mime = $this->entity->{'filemime'}->value;
    $base64Image = base64_encode(file_get_contents($this->getSRC($style)));

    return 'data:' . $mime . ';base64,' . $base64Image;
  }

  /**
   * {@inheritdoc}
   *
   * @param string $style (optional) the image style you want to apply
   * @return string
   */
  public function getRealSrc(string $style = NULL): string
  {
    if (!empty($style)) {
      $imageStyle = ImageStyle::load($style);
      if (!empty($imageStyle)) {
        $url = $imageStyle->buildUrl($this->entity->{'uri'}->value);
        return $url;
      }
    }

    return parent::getRealSrc();
  }

  /**
   * {@inheritdoc}
   *
   * @param string $style (optional) the image style you want to apply
   * @return string
   */
  public function getSRC(string $style = NULL): string
  {
    $url = parent::getSRC();
    if (!empty($style) && !empty($url)) {
      $url .= '&style=' . $style;
    }

    return $url;
  }
}
