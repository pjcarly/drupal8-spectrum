<?php

namespace Drupal\spectrum\Services;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Bytes;
use Drupal\Component\Utility\Environment;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\field\FieldConfigInterface;
use Drupal\spectrum\Exceptions\NotImplementedException;
use Drupal\spectrum\Services\ModelServiceInterface;
use Drupal\spectrum\Models\File;
use Drupal\spectrum\Services\PermissionServiceInterface;
use Drupal\spectrum\Serializer\JsonApiErrorNode;
use Drupal\spectrum\Serializer\JsonApiErrorRootNode;
use Drupal\spectrum\Serializer\JsonApiRootNode;
use Drupal\spectrum\Serializer\ModelSerializerInterface;
use Drupal\spectrum\Utils\StringUtils;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class FileService implements FileServiceInterface
{
  protected LoggerInterface $logger;
  protected ModelServiceInterface $modelService;
  protected PermissionServiceInterface $permissionService;
  protected ModelSerializerInterface $modelSerializer;
  protected Token $tokenService;
  protected FileSystemInterface $fileSystem;
  protected StreamWrapperManagerInterface $streamWrapperManager;

  public function __construct(
    LoggerInterface $logger,
    ModelServiceInterface $modelService,
    PermissionServiceInterface $permissionService,
    ModelSerializerInterface $modelSerializer,
    Token $tokenService,
    FileSystemInterface $fileSystem,
    StreamWrapperManagerInterface $streamWrapperManager
  ) {
    $this->logger = $logger;
    $this->modelService = $modelService;
    $this->permissionService = $permissionService;
    $this->modelSerializer = $modelSerializer;
    $this->tokenService = $tokenService;
    $this->fileSystem = $fileSystem;
    $this->streamWrapperManager = $streamWrapperManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetFromRequest(Request $request): ?string
  {
    return $request->headers->get('X-Mist-Field-Target');
  }

  /**
   * {@inheritdoc}
   */
  public function createNewFile(string $uriScheme, string $directory, string $filename, $data): File
  {
    $directory = trim(trim($directory), '/');
    // Replace tokens. As the tokens might contain HTML we convert it to plaintext.
    $directory = PlainTextOutput::renderFromHtml($this->tokenService->replace($directory, []));
    $filename = basename($filename);

    // We build the URI
    $target = $uriScheme . '://' . $directory;

    // Prepare the destination directory.
    if ($this->fileSystem->prepareDirectory($target, FileSystemInterface::CREATE_DIRECTORY)) {
      // The destination is already a directory, so append the source basename.
      $target = $this->streamWrapperManager->normalizeUri($target . '/' . $this->fileSystem->basename($filename));

      // Create or rename the destination
      $this->fileSystem->getDestinationFilename($target, FileSystemInterface::EXISTS_RENAME);

      // Save the blob in a File Entity
      $fileEntity = file_save_data($data, $target, FileSystemInterface::EXISTS_RENAME);
      $file = new File($fileEntity); // TODO File/Image model fix
      // we want the file to dissapear when it is not attached to a record
      // we put the status on 0, if it is attached somewhere, Drupal will make sure it is not deleted
      // When the attached record is deleted, the corresponding file will follow suit aswell.
      // 6 hours after last modified date for a file, and not attached to a record, cron will clean up the file
      $file->entity->{'status'}->value = 0;
      $file->save();

      return $file;
    } else {
      // Perhaps $destination is a dir/file?
      $dirname = $this->fileSystem->dirname($target);
      if (!$this->fileSystem->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY)) {
        throw new \Exception('File could not be moved/copied because the destination directory ' . $target . ' is not configured correctly.');
      } else {
        throw new NotImplementedException('Functionality not implemented');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handleUploadForTarget(?string $target): Response
  {
    $root = new JsonApiErrorRootNode();
    $responseCode = Response::HTTP_BAD_REQUEST;

    if (isset($_FILES['file'])) {
      if ($_FILES['file']['error'] === UPLOAD_ERR_OK && file_exists($_FILES['file']['tmp_name'])) {
        $file = $_FILES['file'];

        if (!empty($file)) {
          if (isset($target)) {
            $fieldOptions = $this->getFieldConfigForFieldTarget($target);

            if ($fieldOptions) {
              $fieldSettings = $fieldOptions->getSettings();
              $allowedExtensions = explode(' ', $fieldSettings['file_extensions']);
              $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

              if (in_array($fileExtension, $allowedExtensions)) {
                $uriScheme = $fieldSettings['uri_scheme'];
                $targetDirectory = $fieldSettings['file_directory'];

                // Cap the upload size according to the PHP limit.
                $maxFilesize = Bytes::toNumber(Environment::getUploadMaxSize());
                if (!empty($fieldSettings['max_filesize'])) {
                  $maxFilesize = min($maxFilesize, Bytes::toNumber($fieldSettings['max_filesize']));
                }

                if ($file['size'] <= $maxFilesize) {
                  $fileName = basename($_FILES['file']['name']);
                  $data = file_get_contents($_FILES['file']['tmp_name']);

                  try {
                    $file = $this->createNewFile($uriScheme, $targetDirectory, $fileName, $data);

                    $root = new JsonApiRootNode();
                    $node = $file->getJsonApiNode();
                    $root->addNode($node);

                    $responseCode = Response::HTTP_OK;
                  } catch (\Exception $e) {
                    $this->logger->error($e->getMessage() . ' ' . $e->getTraceAsString());

                    $root = new JsonApiErrorRootNode();
                    $node = new JsonApiErrorNode();
                    $node->setStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
                    $node->setCode('SERVER_ERROR');
                    $node->setDetail('Something went wrong, please try again later');
                    $root->addError($node);
                    $responseCode = Response::HTTP_INTERNAL_SERVER_ERROR;
                  }
                } else {
                  $node = new JsonApiErrorNode();
                  $node->setStatus(Response::HTTP_BAD_REQUEST);
                  $node->setCode('FILE_TOO_LARGE');
                  $node->setDetail('File is too large');
                  $root->addError($node);
                  $responseCode = Response::HTTP_BAD_REQUEST;
                }
              } else {
                $node = new JsonApiErrorNode();
                $node->setStatus(Response::HTTP_BAD_REQUEST);
                $node->setCode('FILE_ERR_EXTENSION');
                $node->setDetail('File extension not allowed');
                $root->addError($node);
                $responseCode = Response::HTTP_BAD_REQUEST;
              }
            } else {
              $node = new JsonApiErrorNode();
              $node->setStatus(Response::HTTP_BAD_REQUEST);
              $node->setCode('FIELD_NOT_ACCESSIBLE');
              $node->setDetail('No access to the field');
              $root->addError($node);

              $responseCode = Response::HTTP_BAD_REQUEST;
            }
          }
        } else {
          $node = new JsonApiErrorNode();
          $node->setStatus(Response::HTTP_BAD_REQUEST);
          $node->setCode('FILE_NO_FILE');
          $node->setDetail('No file provided');
          $root->addError($node);

          $responseCode = Response::HTTP_BAD_REQUEST;
        }
      } else if ($_FILES['file']['error'] === UPLOAD_ERR_INI_SIZE) {
        $node = new JsonApiErrorNode();
        $node->setStatus(Response::HTTP_BAD_REQUEST);
        $node->setCode('FILE_TOO_LARGE');
        $node->setDetail('File is too large');
        $root->addError($node);

        $responseCode = Response::HTTP_BAD_REQUEST;
      } else if ($_FILES['file']['error'] === UPLOAD_ERR_FORM_SIZE) {
        $node = new JsonApiErrorNode();
        $node->setStatus(Response::HTTP_BAD_REQUEST);
        $node->setCode('FILE_TOO_LARGE');
        $node->setDetail('File is too large');
        $root->addError($node);

        $responseCode = Response::HTTP_BAD_REQUEST;
      } else if ($_FILES['file']['error'] === UPLOAD_ERR_PARTIAL) {
        $node = new JsonApiErrorNode();
        $node->setStatus(Response::HTTP_BAD_REQUEST);
        $node->setCode('ERR_PARTIAL');
        $node->setDetail('File upload partial error');
        $root->addError($node);

        $responseCode = Response::HTTP_BAD_REQUEST;
      } else if ($_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        $node = new JsonApiErrorNode();
        $node->setStatus(Response::HTTP_BAD_REQUEST);
        $node->setCode('FILE_NO_FILE');
        $node->setDetail('No file provided');
        $root->addError($node);

        $responseCode = Response::HTTP_BAD_REQUEST;
      } else if ($_FILES['file']['error'] === UPLOAD_ERR_NO_TMP_DIR) {

        $node = new JsonApiErrorNode();
        $node->setStatus(Response::HTTP_BAD_REQUEST);
        $node->setCode('FILE_NO_TMP');
        $node->setDetail('File no temp');
        $root->addError($node);
        $responseCode = Response::HTTP_BAD_REQUEST;
      } else if ($_FILES['file']['error'] === UPLOAD_ERR_EXTENSION) {
        $node = new JsonApiErrorNode();
        $node->setStatus(Response::HTTP_BAD_REQUEST);
        $node->setCode('FILE_ERR_EXTENSION');
        $node->setDetail('File extension not allowed');
        $root->addError($node);

        $responseCode = Response::HTTP_BAD_REQUEST;
      } else if ($_FILES['file']['error'] === UPLOAD_ERR_CANT_WRITE) {
        $node = new JsonApiErrorNode();
        $node->setStatus(Response::HTTP_BAD_REQUEST);
        $node->setCode('FILE_CANT_WRITE');
        $node->setDetail('File cant write');
        $root->addError($node);

        $responseCode = Response::HTTP_BAD_REQUEST;
      }
    } else {
      $node = new JsonApiErrorNode();
      $node->setStatus(Response::HTTP_BAD_REQUEST);
      $node->setCode('UPLOAD_FAILED');
      $node->setDetail('File upload failed');
      $root->addError($node);

      $responseCode = Response::HTTP_BAD_REQUEST;
    }

    /** @var JsonApiRootNode|JsonApiErrorRootNode $root */
    return new Response(
      empty($root) ? null : json_encode($root->serialize()),
      $responseCode,
      ['Content-Type' => JsonApiRootNode::HEADER_CONTENT_TYPE]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldConfigForFieldTarget(string $target): ?FieldConfigInterface
  {
    $returnValue = null;

    $modelClasses = $this->modelService->getRegisteredModelClasses();
    $explodedTarget = explode('.', $target);

    if ($explodedTarget && sizeof($explodedTarget) === 2) {
      $dasherizedModel = $explodedTarget[0];
      $dasherizedField = $explodedTarget[1];

      foreach ($modelClasses as $modelClass) {
        $dasherizedBundleKey = StringUtils::dasherize($this->modelService->getBundleKey($modelClass));

        if ($dasherizedBundleKey === $dasherizedModel) {
          $ignoreFields = $this->modelSerializer->getDefaultIgnoreFields();
          $fieldToPrettyMapping = $this->modelSerializer->getFieldsToPrettyFieldsMapping($modelClass);

          foreach ($this->modelService->getFieldDefinitions($modelClass) as $fieldName => $fieldDefinition) {
            if (!in_array($fieldName, $ignoreFields)) {
              $fieldNamePretty = $fieldToPrettyMapping[$fieldName];

              if ($fieldNamePretty === $dasherizedField) {
                if ($this->permissionService->currentUserHasFieldEditPermission($modelClass, $fieldName)) {
                  $returnValue = $fieldDefinition;
                }
                break;
              }
            }
          }
          break;
        }
      }
    }

    return $returnValue;
  }
}
