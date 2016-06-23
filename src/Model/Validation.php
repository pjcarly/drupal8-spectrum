<?php
namespace Drupal\spectrum\Model;

use Drupal\Component\Utility\Html;

class Validation
{
  private $model;
  private $violations;
  private $modelName;

	public function __construct($model)
	{
		$this->model = $model;
    $this->modelName = $model->getModelName();
    $this->violations = $model->entity->validate();
	}

  public function getViolations()
  {
    return $this->violations;
  }

  public function addViolation($violation)
  {
    $this->violations->add($violation);
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

    $modelName = $this->modelName;
    $fieldToPrettyMapping = $modelName::getFieldsToPrettyFieldsMapping();
    foreach($this->violations as $violation)
    {
      if ($path = $violation->getPropertyPath())
      {
        list($fieldName) = explode('.', $path, 2);

        if (array_key_exists($fieldName, $fieldToPrettyMapping))
        {
          $prettyField = $fieldToPrettyMapping[$fieldName];
          $error = new \stdClass();
          $error->detail = strip_tags($violation->getMessage()->render());
          $error->source = new \stdClass();
          $error->source->pointer = 'data/attributes/'.$prettyField;
          $errors->errors[] = $error;
        }
        else
        {
          $error = new \stdClass();
          $error->detail = strip_tags($violation->getMessage()->render() . ' ('.$fieldName.')');
          $error->source = new \stdClass();
          $error->source->pointer = 'data';
          $errors->errors[] = $error;
        }
      }
      else
      {
        $error = new \stdClass();
        $error->detail = strip_tags($violation->getMessage()->render());
        $error->source = new \stdClass();
        $error->source->pointer = 'data';
        $errors->errors[] = $error;
      }
    }

    return $errors;
  }

  public function serialize()
  {
    return $this->toJsonApi();
  }
}
