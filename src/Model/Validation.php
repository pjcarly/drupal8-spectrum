<?php
namespace Drupal\spectrum\Model;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;

class Validation
{
  private $model;
  private $violations;
  private $modelName;
  private $ignores = [];
  private $inlineValidations = [];

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
      // life is easy, no violations counted, if the inlineValidationsSucceed we are ready to go!
      return $this->inlineValidationsSucceeded();
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

      return $foundViolationCount === 0 && $this->inlineValidationsSucceeded();
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

  public function inlineValidationsSucceeded()
  {
    $succeeded = true;
    foreach($this->inlineValidations as $inlineValidation)
    {
      if(!$inlineValidation->hasSucceeded())
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

  public function processInvalidReferenceConstraints()
  {
    // this method is a workaround for a failed validation, where a entity reference field is filled in with a reference to an entity that no longer exists
    // because it has been deleted.
    // In this method, we set all those values to null
    foreach($this->violations as $violation)
    {
      $failingConstraint = $violation->getConstraint();

      if($failingConstraint instanceof ValidReferenceConstraint)
      {
        if ($path = $violation->getPropertyPath())
        {
          $fieldName = $this->getFieldNameForPropertyPath($path);
          $this->model->entity->$fieldName->target_id = null;
          $this->addIgnore($fieldName, 'Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint');
        }
      }
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

  public function addIncludedValidation($path, Validation $validation)
  {
    $this->inlineValidations[$path] = $validation;
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
            $error->detail = strip_tags('('.$prettyField.') ' . $violation->getMessage()->render());
            $error->source = new \stdClass();
            $error->source->pointer = '/data/attributes/'.$prettyField;
            $errors->errors[] = $error;
          }
          else
          {
            $error = new \stdClass();
            $error->detail = strip_tags($violation->getMessage()->render());
            $error->source = new \stdClass();
            $error->source->pointer = '/data';
            $errors->errors[] = $error;
          }
        }
        else
        {
          $error = new \stdClass();
          $error->detail = strip_tags($violation->getMessage()->render());
          $error->source = new \stdClass();
          $error->source->pointer = '/data';
          $errors->errors[] = $error;
        }
      }
    }

    // we must also include the inline validations
    foreach($this->inlineValidations as $path => $inlineValidation)
    {
      $inlineRecordsErrors = $inlineValidation->toJsonApi();
      foreach($inlineRecordsErrors as $inlineRecordErrors)
      {
        foreach($inlineRecordErrors as $inlineRecordError)
        {
          // we must include the path in the error pointer
          $inlineRecordError->source->pointer = '/data'. $path . $inlineRecordError->source->pointer;
          $errors->errors[] = $inlineRecordError;
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
