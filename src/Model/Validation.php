<?php

namespace Drupal\spectrum\Model;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Symfony\Component\Validator\ConstraintViolation;

/**
 * This class is a wrapper around the Drupal validation API, it extends the functionality by making it jsonapi.org compliant and serializable
 */
class Validation
{
  /**
   * The model where we are doing the validation on
   *
   * @var Model
   */
  private $model;

  /**
   * The entity constraint violations
   *
   * @var EntityConstraintViolationListInterface
   */
  private $violations;

  /**
   * The fully qualified classname of the Model doing the validation
   *
   * @var string
   */
  private $modelName;

  /**
   * An array with as Key the fieldname, and as value the fully qualified classname of the constraint to ignore
   *
   * @var array
   */
  private $ignores = [];

  /**
   * The inline validations, with as key the inline relationship name, and as value, another Validation object
   *
   * @var array
   */
  private $inlineValidations = [];

  /**
   * The model you want to validate
   *
   * @param Model $model
   */
  public function __construct(Model $model)
  {
    $this->model = $model;
    $this->modelName = $model->getModelName();
    $this->violations = $model->entity->validate();
  }

  /**
   * Returns the Violations after validating the model
   *
   * @return EntityConstraintViolationListInterface
   */
  public function getViolations(): EntityConstraintViolationListInterface
  {
    return $this->violations;
  }

  /**
   * Add a ConstraintViolation to the violations
   *
   * @param ConstraintViolation $violation
   * @return Validation
   */
  public function addViolation(ConstraintViolation $violation): Validation
  {
    $this->violations->add($violation);
    return $this;
  }

  /**
   * Return an array of all the fieldnames that have a violation
   *
   * @return array
   */
  public function getFailedFields(): array
  {
    return $this->violations->getFieldNames();
  }

  /**
   * Return the violations of the failed fields
   *
   * @return EntityConstraintViolationListInterface
   */
  public function getValidationErrors(): EntityConstraintViolationListInterface
  {
    $failedFields = $this->getFailedFields();
    return $this->violations->getByFields($failedFields);
  }

  /**
   * Checks if the validations succeeded, it will correctly ignore  all ignores.
   *
   * @return boolean
   */
  public function hasSucceeded(): bool
  {
    if ($this->violations->count() === 0) {
      // life is easy, no violations counted, if the inlineValidationsSucceed we are ready to go!
      return $this->inlineValidationsSucceeded();
    } else if (sizeof($this->ignores) === 0) {
      // life is still easy, there were violations, but no ignores, so the validation failed
      return false;
    } else {
      // now it gets hard, there were violations, but also ignores, we must check if the violations found include our ignore
      // we will count the violations
      $foundViolationCount = $this->violations->count();
      foreach ($this->violations as $violation) {
        if ($this->hasIgnoreForViolation($violation)) {
          // we found an ignore, let's deduct the count
          $foundViolationCount--;
        }
      }

      return $foundViolationCount === 0 && $this->inlineValidationsSucceeded();
    }
  }

  /**
   * Check if there is an ignore that exists for the provided violation
   *
   * @param ConstraintViolation $violation
   * @return boolean
   */
  private function hasIgnoreForViolation(ConstraintViolation $violation): bool
  {
    $ignoreFound = false;
    if ($path = $violation->getPropertyPath()) {
      // lets get the fieldName from the violation
      $fieldName = $this->getFieldNameForPropertyPath($path);
      if (array_key_exists($fieldName, $this->ignores)) {
        // we found an ignore for this field
        // lets check the class
        $failingConstraint = $violation->getConstraint();
        $constraintToIgnore = $this->ignores[$fieldName];
        if ($failingConstraint instanceof $constraintToIgnore) {
          // aha, class matches, so there is an ignore;
          $ignoreFound = true;
        }
      }
    }
    return $ignoreFound;
  }

  /**
   * Check if all inline validations succeeded
   *
   * @return boolean
   */
  public function inlineValidationsSucceeded(): bool
  {
    $succeeded = true;
    foreach ($this->inlineValidations as $inlineValidation) {
      if (!$inlineValidation->hasSucceeded()) {
        $succeeded = false;
        break;
      }
    }
    return $succeeded;
  }

  /**
   * The Inverse of hasSucceeded()
   *
   * @return boolean
   */
  public function hasFailed(): bool
  {
    return !$this->hasSucceeded();
  }

  /**
   * This method is a workaround for a failed validation, where a entity reference field is filled in with a reference to an entity
   * that no longer exists because it has been deleted.
   * In this method, we set all those values to null
   *
   * @return Validation
   */
  public function processInvalidReferenceConstraints(): Validation
  {
    foreach ($this->violations as $violation) {
      $failingConstraint = $violation->getConstraint();

      if ($failingConstraint instanceof ValidReferenceConstraint) {
        if ($path = $violation->getPropertyPath()) {
          $fieldName = $this->getFieldNameForPropertyPath($path);
          $this->model->entity->$fieldName->target_id = null;
          $this->addIgnore($fieldName, 'Drupal\Core\Entity\Plugin\Validation\Constraint\ValidReferenceConstraint');
        }
      }
    }

    return $this;
  }

  /**
   * Merge 1 validation with all the violations of another Validation object
   *
   * @param Validation $validation
   * @return Validation
   */
  public function merge(Validation $validation): Validation
  {
    $this->violations->addAll($validation->getViolations());
    return $this;
  }

  /**
   * With this function we can add a specific constraint ignore for a field, this way the validation will not fail even though the constraint did
   * this is very useful when validating a parent model with a child collection
   * if the parent is required on the child, but both are not yet saved to the db, then the parent will not have an id,
   * and thus the child will always fail with a notnull constraint
   * by adding the ignore, the validation will not fail, and model + collection can do it's job later on by inserting the parent
   * putting the parentid on the children, and then inserting the children
   *
   * @param string $fieldName The drupal fieldname you want to add an Ignore on
   * @param string $constraint The fully qualified classname of the Drupal Constraint you want to ignore
   * @return Validation
   */
  public function addIgnore(string $fieldName, string $constraint): Validation
  {
    $this->ignores[$fieldName] = $constraint;
    return $this;
  }

  /**
   * Add an Included validation, these are validations that are not of the entity, but of an included relationship in the jsonapi.org hash
   *
   * @param string $path
   * @param Validation $validation
   * @return Validation
   */
  public function addIncludedValidation(string $path, Validation $validation): Validation
  {
    $this->inlineValidations[$path] = $validation;
    return $this;
  }

  /**
   * Parse the fieldname out of the property path
   *
   * @param string $propertyPath
   * @return string
   */
  private function getFieldNameForPropertyPath(string $propertyPath): string
  {
    list($fieldName) = explode('.', $propertyPath, 2);
    return $fieldName;
  }

  /**
   * Returns a PHP stdClass which can be JSON serialized to a jsonapi.org compliant errors hash
   *
   * @return \stdClass
   */
  public function toJsonApi(): \stdClass
  {
    $errors = new \stdClass();
    $errors->errors = [];

    $modelName = $this->modelName;
    $fieldToPrettyMapping = $modelName::getFieldsToPrettyFieldsMapping();
    foreach ($this->violations as $violation) {
      // Let's check for ignores
      if (!$this->hasIgnoreForViolation($violation)) {
        if ($path = $violation->getPropertyPath()) {
          $fieldName = $this->getFieldNameForPropertyPath($path);

          if (array_key_exists($fieldName, $fieldToPrettyMapping)) {
            $prettyField = $fieldToPrettyMapping[$fieldName];
            $error = new \stdClass();
            $error->detail = strip_tags('(' . $prettyField . ') ' . $violation->getMessage()->render());
            $error->source = new \stdClass();
            $error->source->pointer = '/data/attributes/' . $prettyField;
            $errors->errors[] = $error;
          } else {
            $error = new \stdClass();
            $error->detail = strip_tags($violation->getMessage()->render());
            $error->source = new \stdClass();
            $error->source->pointer = '/data';
            $errors->errors[] = $error;
          }
        } else {
          $error = new \stdClass();
          $error->detail = strip_tags($violation->getMessage()->render());
          $error->source = new \stdClass();
          $error->source->pointer = '/data';
          $errors->errors[] = $error;
        }
      }
    }

    // we must also include the inline validations
    foreach ($this->inlineValidations as $path => $inlineValidation) {
      $inlineRecordsErrors = $inlineValidation->toJsonApi();
      foreach ($inlineRecordsErrors as $inlineRecordErrors) {
        foreach ($inlineRecordErrors as $inlineRecordError) {
          // we must include the path in the error pointer
          $inlineRecordError->source->pointer = '/data' . $path . $inlineRecordError->source->pointer;
          $errors->errors[] = $inlineRecordError;
        }
      }
    }

    return $errors;
  }

  /**
   * Alias of toJsonApi()
   *
   * @return \stdClass
   */
  public function serialize(): \stdClass
  {
    return $this->toJsonApi();
  }
}
