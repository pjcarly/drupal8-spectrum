<?php
namespace Drupal\spectrum\Model;

use Drupal\Component\Utility\Html;

class Validation
{
  private $model;
  private $violations;
  private $modelName;
  private $ignores = [];
  private $childValidations = [];

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
    if($this->violations->count() === 0)
    {
      // life is easy, no violations counted, if the childValidationsSucceed we are go!
      return $this->childValidationsSucceeded();
    }
    else if (sizeof($this->ignores) === 0)
    {
      // life is still easy, there were violations, but no ignores, so the validation failed
      return false;
    }
    else
    {
      // now it gets hard, there were violations, but also ignores, we must check if the violations found include our ignore
      // we will count the violations
      $foundViolationCount = $this->violations->count();
      foreach($this->violations as $violation)
      {
        if($this->hasIgnoreForViolation($violation))
        {
          // we found an ignore, let's deduct the count
          $foundViolationCount--;
        }
      }

      return $foundViolationCount === 0 && $this->childValidationsSucceeded();
    }
  }

  private function hasIgnoreForViolation($violation)
  {
    $ignoreFound = false;
    if($path = $violation->getPropertyPath())
    {
      // lets get the fieldName from the violation
      $fieldName = $this->getFieldNameForPropertyPath($path);
      if(array_key_exists($fieldName, $this->ignores))
      {
        // we found an ignore for this field
        // lets check the class
        $failingConstraint = $violation->getConstraint();
        $constraintToIgnore = $this->ignores[$fieldName];
        if($failingConstraint instanceof $constraintToIgnore)
        {
          // aha, class matches, so there is an ignore;
          $ignoreFound = true;
        }
      }
    }
    return $ignoreFound;
  }

  public function childValidationsSucceeded()
  {
    $succeeded = true;
    foreach($this->childValidations as $childValidation)
    {
      if(!$childValidation->hasSucceeded())
      {
        $succeeded = false;
        break;
      }
    }
    return $succeeded;
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

  public function addIgnore($fieldName, $constraint)
  {
    // with this function we can add a specific constraint ignore for a field, this way the validation will not fail even though the constraint did
    // this is very useful when validating a parent model with a child collection
    // if the parent is required on the child, but both are not yet saved to the db, then the parent will not have an id,
    // and thus the child will always fail with a notnull constraint
    // by adding the ignore, the validation will not fail, and model + collection can do it's job later on by inserting the parent
    // putting the parentid on the children, and then inserting the children
    $this->ignores[$fieldName] = $constraint;
  }

  public function addChildValidation($path, Validation $validation)
  {
    $this->childValidations[$path] = $validation;
  }

  private function getFieldNameForPropertyPath($propertyPath)
  {
    list($fieldName) = explode('.', $propertyPath, 2);
    return $fieldName;
  }

  public function toJsonApi()
  {
    $errors = new \stdClass();
    $errors->errors = array();

    $modelName = $this->modelName;
    $fieldToPrettyMapping = $modelName::getFieldsToPrettyFieldsMapping();
    foreach($this->violations as $violation)
    {
      // Let's check for ignores
      if(!$this->hasIgnoreForViolation($violation))
      {
        if ($path = $violation->getPropertyPath())
        {
          $fieldName = $this->getFieldNameForPropertyPath($path);

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
    }

    // we must also include the child validations
    foreach($this->childValidations as $path => $childValidation)
    {
      $childRecordsErrors = $childValidation->toJsonApi();
      foreach($childRecordsErrors as $childRecordErrors)
      {
        foreach($childRecordErrors as $childRecordError)
        {
          // we must include the path in the error pointer
          $childRecordError->source->pointer = $path . $childRecordError->source->pointer;
          $errors->errors[] = $childRecordError;
        }
      }
    }

    return $errors;
  }

  public function serialize()
  {
    return $this->toJsonApi();
  }
}
