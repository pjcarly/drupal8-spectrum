<?php

namespace Drupal\spectrum\Models;

use Drupal\image\Entity\ImageStyle;

class Image extends File
{
  public static $plural = 'Images';

  public static function relationships()
  {
  }

  protected function getBaseApiPath() : string
  {
    return 'image';
  }

  public function getBase64SRC(string $style = NULL) : string
  {
    $mime = $this->entity->get('filemime')->value;
    $base64Image = base64_encode(file_get_contents($this->getSRC($style)));

    return 'data:'.$mime.';base64,'.$base64Image;
  }

  public function getRealSrc(string $style = NULL) : string
  {
    if(!empty($style))
    {
      $imageStyle = ImageStyle::load($style);
      if(!empty($imageStyle))
      {
        $url = $imageStyle->buildUrl($this->entity->get('uri')->value);
        return $url;
      }
    }

    return parent::getRealSrc();
  }

  public function getSRC(string $style = NULL) : string
  {
    $url = parent::getSRC();
    if(!empty($style) && !empty($url))
    {
      $url .= '&style='.$style;
    }

    return $url;
  }
}
