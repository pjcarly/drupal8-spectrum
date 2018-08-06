<?php

namespace Drupal\spectrum\Email;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Template\TwigRenderer;

class EmailTemplate extends Model
{
  public static $entityType = 'template';
  public static $bundle = 'email';
  public static $idField = 'id';
  public static $plural = 'Email Templates';

  private $scope = [];
  public $subject = '';
  public $html = '';
  public $text = '';

  /**
   * Add a Model to the Scope of this email template, then the fields on the model can be used in the email template
   *
   * @param string $name
   * @param Model $model
   * @return EmailTemplate
   */
  public function addModelToScope(string $name, Model $model) : EmailTemplate
  {
    $this->scope[$name] = new SimpleModelWrapper($model);

    return $this;
  }

  /**
   * Add a generic object to the scope of this email template, properties on this object can be used in the email template
   *
   * @param string $name
   * @param [type] $object
   * @return EmailTemplate
   */
  public function addObjectToScope(string $name, $object) : EmailTemplate
  {
    $this->scope[$name] = $object;

    return $this;
  }

  /**
   * Renders the current email template, after the twig template will be rendered, 3 properties on this object will be filled, "subject", html", "text"
   *
   * @return EmailTemplate
   */
  public function render() : EmailTemplate
  {
    // Use the spectrum twigrenderer;
    $twig = new TwigRenderer();

    // Lets render the different parts of the email template
    $this->subject = $twig->render($this->entity->field_subject->value, $this->scope);
    $this->html = $twig->render($this->entity->field_html_body->value, $this->scope);
    $this->text = $twig->render($this->entity->field_text_body->value, $this->scope);

    return $this;
  }

  /**
   * Fetch an EmailTemplate from the database based on the field_key
   *
   * @param string $name
   * @return EmailTemplate
   */
  public static function getByKey(string $key) : EmailTemplate
  {
    $query = new ModelQuery('Drupal\spectrum\Email\EmailTemplate');
    $query->addCondition(new Condition('field_key', '=', $key));
    return $query->fetchSingleModel();
  }
}
