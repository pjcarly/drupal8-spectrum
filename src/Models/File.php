<?php

namespace Drupal\spectrum\Models;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;

class File extends Model
{
	public static $entityType = 'file';
	public static $idField = 'fid';

  public static $plural = 'Files';

  public static function relationships()
	{
  }

  protected function getBaseApiPath()
  {
    return 'file';
  }

  public function getJsonApiNode()
  {
    $hash = md5($this->entity->uuid->value);

    $request = \Drupal::request();
    $url = $request->getSchemeAndHttpHost() . $request->getBasePath() . '/'.$this->getBaseApiPath().'/' . $this->entity->get('filename')->value . '/?fid=' . $this->getId() . '&dg=' . $hash;

    $node = parent::getJsonApiNode();
    $node->addAttribute('hash', $hash);
    $node->addAttribute('url', $url);

    return $node;
  }

  public function getBase64SRC()
  {
    $mime = $this->entity->get('filemime')->value;
    $base64 = base64_encode(file_get_contents($this->getSRC()));

    return 'data:'.$mime.';base64,'.$base64;
  }

  public function getSRC(string $style = NULL)
  {
    return $this->entity->url();
  }
}
