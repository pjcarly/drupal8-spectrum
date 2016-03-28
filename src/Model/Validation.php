<?php
namespace Drupal\spectrum\Model;

use Drupal\spectrum\Serializer\ModelSerializerBase;
use Drupal\Component\Utility\Html;

class Validation extends ModelSerializerBase
{
  private $model;
  private $violations;

	public function __construct($model)
	{
    parent::__construct($model->getModelName());
		$this->model = $model;
    $this->violations = $model->entity->validate();
	}

  public function getViolations()
  {
    return $this->violations;
  }

  public function getFailedFields()
  {
    return $this->violations->getFieldNames();
  }

  public function getValidationErrors()
  {
    $failedFields = $this->getFailedFields();
    return $this->violations->getByFields($failedFields);
  }

  public function hasSucceeded()
  {
    return $this->violations->count() === 0;
  }

  public function hasFailed()
  {
    return !$this->hasSucceeded();
  }

  public function debug()
  {
    foreach($this->violations as $violation)
    {
      dump($violation);
    }
  }

  public function merge(Validation $validation)
  {
    $this->violations->addAll($validation->getViolations());
  }

  public function toJsonApi()
  {
    $errors = new \stdClass();
    $errors->errors = array();

    // First we handle all the entity level violations (violations not on property/field level)
    foreach($this->violations->getEntityViolations() as $violation)
    {
      $error = new \stdClass();
      $error->detail = strip_tags($violation->getMessage()->render());
      $error->source = new \stdClass();
      $error->source->pointer = 'data';
      $errors->errors[] = $error;
    }

    // And next we handle all the property violations
    $fieldToPrettyMapping = $this->getFieldsToPrettyFieldsMapping();
    foreach($this->getFailedFields() as $fieldName)
    {
      $prettyField = $fieldToPrettyMapping[$fieldName];
      foreach($this->violations->getByField($fieldName) as $violation)
      {
        $error = new \stdClass();
        $error->detail = strip_tags($violation->getMessage()->render());
        $error->source = new \stdClass();
        $error->source->pointer = 'data/attributes/'.$prettyField;
        $errors->errors[] = $error;
      }
    }

    return $errors;
  }
}
