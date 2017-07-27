<?php

namespace Drupal\spectrum\Models;

use Drupal\spectrum\Model\Model;

use Drupal\image\Entity\ImageStyle;

class Image extends Model
{
	public static $entityType = 'file';
	public static $idField = 'fid';

  public static $plural = 'Files';

  public static function relationships()
	{
	}

  public function getBase64SRC(string $style = NULL)
  {
    $mime = $this->entity->get('filemime')->value;
    $base64Image = base64_encode(file_get_contents($this->getSRC($style)));

    return 'data:'.$mime.';base64,'.$base64Image;
  }

  public function getSRC(string $style = NULL)
  {
    $url;
    if(empty($style))
    {
      $url = $this->entity->url();
    }
    else
    {
      $imageStyle = ImageStyle::load($style);
      if(!empty($imageStyle))
      {
        $url = $imageStyle->buildUrl($this->entity->get('uri')->value);
      }
    }

    return $url;
  }
}
