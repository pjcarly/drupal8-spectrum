<?php

namespace Drupal\spectrum\Models;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\FieldRelationship;
use Drupal\spectrum\Model\ReferencedRelationship;
use Drupal\spectrum\Permissions\AccessPolicy\AccessPolicyInterface;
use Drupal\spectrum\Permissions\AccessPolicy\PublicAccessPolicy;
use Drupal\spectrum\Serializer\JsonApiNode;
use Drupal\spectrum\Utils\UrlUtils;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\spectrum\Exceptions\NotImplementedException;

/**
 * A File model for the file entity
 */
class File extends Model
{
  /**
   * The Entitytype of this model
   *
   * @return string
   */
  public static function entityType(): string
  {
    return 'file';
  }

  /**
   * The Bundle of this model
   *
   * @return string
   */
  public static function bundle(): string
  {
    return '';
  }

  /**
   * The relationships to other models
   *
   * @return void
   */
  public static function relationships()
  { }

  /**
   * @inheritDoc
   */
  public static function getAccessPolicy(): AccessPolicyInterface
  {
    return new PublicAccessPolicy;
  }

  /**
   * This function can be used in dynamic api handlers
   *
   * @return string
   */
  protected function getBaseApiPath(): string
  {
    return 'file';
  }

  /**
   * Returns an array of the references where this File is being used. These are fields that contain the file
   *
   * @return array
   */
  public function getReferences(): array
  {
    return file_get_file_references($this->entity);
  }

  /**
   * Returns an array of file usage in the file_usage table
   *
   * @return array
   */
  public function getUsage(): array
  {
    $fileUsageService = \Drupal::service('file.usage');
    $usage = $fileUsageService->listUsage($this->entity);

    return empty($usage) ? [] : $usage;
  }

  /**
   * Returns true if this file is referenced in other entities
   *
   * @return boolean
   */
  public function hasReferences(): bool
  {
    return sizeof($this->getReferences()) > 0;
  }

  /**
   * Returns true if the file exists in the file_usage table
   *
   * @return boolean
   */
  public function hasUsage(): bool
  {
    return sizeof($this->getUsage()) > 0;
  }

  /**
   * Returns true if the file has references or is listed in the file_usage table
   *
   * @return boolean
   */
  public function isInUse(): bool
  {
    return $this->hasReferences() || $this->hasUsage();
  }

  /**
   * Removes this file from all the references. So if an entity in the system refers to this file. Set that reference to NULL
   *
   * @return void
   */
  public function removeFromReferences()
  {
    $references = $this->getReferences();

    // We loop over the references
    foreach ($references as $fieldName => $referencedEntityTypes) {
      // Next we loop over every entitytype containting the different entities
      foreach ($referencedEntityTypes as $entityType => $referencedEntities) {
        // Next we loop over every entity containing the reference
        foreach ($referencedEntities as $entityId => $entity) {
          $entity->$fieldName->target_id = null;
          $entity->save();
        }
      }
    }
  }

  /**
   * Serializes the model, but also adds the URL to the file, and the hash
   *
   * @return JsonApiNode
   */
  public function getJsonApiNode(): JsonApiNode
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
  public function getHash(): string
  {
    return md5($this->entity->uuid->value);
  }

  /**
   * Return the real SRC of the file, this will return a direct link to the file, specific for the file back-end storage
   *
   * @return string
   */
  public function getRealSrc(): string
  {
    return $this->entity->url();
  }

  /**
   * Returns a base64 encoded string of the file
   *
   * @return string
   */
  public function getBase64SRC(): string
  {
    $mime = $this->entity->get('filemime')->value;
    $base64 = base64_encode(file_get_contents($this->getRealSrc()));

    return 'data:' . $mime . ';base64,' . $base64;
  }

  /**
   * Return a Drupal absolute URL that you can use to return the File indepentenly of the File storage back-end
   * All the information to get the file is contained in the URL. the FID (file ID), and DG an MD5 hash of the UUID (so you can validate the call by an extra param)
   *
   * @return string
   */
  public function getSRC(): string
  {
    $url = UrlUtils::getBaseURL() . $this->getBaseApiPath() . '/' . $this->entity->get('filename')->value . '?fid=' . $this->getId() . '&dg=' . $this->getHash();

    return $url;
  }

  /**
   * Create a new FileModel by saving a data blob, getting the entity from drupal and wrapping it in a model
   *
   * @param string $uriScheme
   * @param string $directory
   * @param string $filename
   * @param mixed $data the blob of the file you want to save
   * @return File
   */
  public static function createNewFile(string $uriScheme, string $directory, string $filename, $data): File
  {
    $directory = trim(trim($directory), '/');
    // Replace tokens. As the tokens might contain HTML we convert it to plaintext.
    $directory = PlainTextOutput::renderFromHtml(\Drupal::token()->replace($directory, []));
    $filename = basename($filename);

    // We build the URI
    $target = $uriScheme . '://' . $directory;

    // Prepare the destination directory.
    if (file_prepare_directory($target, FILE_CREATE_DIRECTORY)) {
      // The destination is already a directory, so append the source basename.
      $target = file_stream_wrapper_uri_normalize($target . '/' . drupal_basename($filename));

      // Create or rename the destination
      file_destination($target, FILE_EXISTS_RENAME);

      // Save the blob in a File Entity
      $fileEntity = file_save_data($data, $target, FILE_EXISTS_RENAME);
      $file = new File($fileEntity); // TODO File/Image model fix
      // we want the file to dissapear when it is not attached to a record
      // we put the status on 0, if it is attached somewhere, Drupal will make sure it is not deleted
      // When the attached record is deleted, the corresponding file will follow suit aswell.
      // 6 hours after last modified date for a file, and not attached to a record, cron will clean up the file
      $file->entity->status->value = 0;
      $file->save();

      return $file;
    } else {
      // Perhaps $destination is a dir/file?
      $dirname = drupal_dirname($target);
      if (!file_prepare_directory($dirname, FILE_CREATE_DIRECTORY)) {
        throw new \Exception('File could not be moved/copied because the destination directory ' . $target . ' is not configured correctly.');
      } else {
        throw new NotImplementedException('Functionality not implemented');
      }
    }
  }
}
