<?php

namespace Drupal\spectrum\Models;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Serializer\JsonApiBaseNode;
use Drupal\spectrum\Utils\UrlUtils;

class File extends Model
{
  public static $entityType = 'file';
  public static $idField = 'fid';

  public static $plural = 'Files';

  public static function relationships()
  {
  }

  protected function getBaseApiPath() : string
  {
    return 'file';
  }

  /**
   * Serializes the model, but also adds the URL to the file, and the hash
   *
   * @return JsonApiBaseNode
   */
  public function getJsonApiNode() : JsonApiBaseNode
  {
    $node = parent::getJsonApiNode();
    $node->addAttribute('hash', $this->getHash());
    $node->addAttribute('url', $this->getSRC());

    return $node;
  }

  /**
   * Returns a hash based on the UUID, with this hash you can validate requests for files, and see if it matches the FID in the database
   *
   * @return string
   */
  public function getHash() : string
  {
    return md5($this->entity->uuid->value);
  }

  /**
   * Return the real SRC of the file, this will return a direct link to the file, specific for the file back-end storage
   *
   * @return string
   */
  public function getRealSrc() : string
  {
    return $this->entity->url();
  }

  /**
   * Returns a base64 encoded string of the file
   *
   * @return string
   */
  public function getBase64SRC() : string
  {
    $mime = $this->entity->get('filemime')->value;
    $base64 = base64_encode(file_get_contents($this->getRealSrc()));

    return 'data:'.$mime.';base64,'.$base64;
  }

  /**
   * Return a Drupal absolute URL that you can use to return the File indepentenly of the File storage back-end
   * All the information to get the file is contained in the URL. the FID (file ID), and DG an MD5 hash of the UUID (so you can validate the call by an extra param)
   *
   * @return string
   */
  public function getSRC() : string
  {
    $url = UrlUtils::getBaseURL() . $this->getBaseApiPath().'/' . $this->entity->get('filename')->value . '?fid=' . $this->getId() . '&dg=' . $this->getHash();

    return $url;
  }

  /**
   * Create a new FileModel by saving a data blob, getting the entity from drupal and wrapping it in a model
   *
   * @param mixed $data
   * @param string $filename
   * @return void
   */
  public static function createNewFile($data, string $filename)
  {
    $fileEntity = file_save_data($data, 's3://'.basename($filename), FILE_EXISTS_RENAME);
    $file = File::forge($fileEntity);
    // we want the file to dissapear when it is not attached to a record
    // we put the status on 0, if it is attached somewhere, Drupal will make sure it is not deleted
    // when the attached record is deleted, the corresponding file will follow suit aswell.
    // 6 hours after last modified date for a file, and not attached to a record, cron will clean up the file
    $file->entity->status->value = 0;
    $file->save();
    return $file;
  }
}
