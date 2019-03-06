<?php

namespace Drupal\spectrum\Email;

use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\SimpleCollectionWrapper;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Template\TwigRenderer;

class EmailTemplate extends Model
{
  /**
   * The Entitytype of this model
   *
   * @return string
   */
  public static function entityType() : string
  {
    return 'template';
  }

  /**
   * The Bundle of this model
   *
   * @return string
   */
  public static function bundle() : string
  {
    return 'email';
  }

  /**
   * The scope that will be added to the EmailTemplate upon rendering, to fetch dynamic variables from
   *
   * @var array
   */
  private $scope = [];

  /**
   * After rendering this variable will hold the Subject
   *
   * @var string
   */
  public $subject = '';

  /**
   * After rendering this variable will hold the HTML Body
   *
   * @var string
   */
  public $html = '';

  /**
   * After rendering this variable will hold the Text Body
   *
   * @var string
   */
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

  public function addCollectionToScope(string $name, Collection $collection) : EmailTemplate
  {
    $this->scope[$name] = new SimpleCollectionWrapper($collection);

    return $this;
  }

  /**
   * Add a generic object to the scope of this email template, properties on this object can be used in the email template
   *
   * @param string $name
   * @param object $object
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
   * @param string $key
   * @return EmailTemplate
   */
  public static function getByKey(string $key) : EmailTemplate
  {
    $query = new ModelQuery('Drupal\spectrum\Email\EmailTemplate');
    $query->addCondition(new Condition('field_key', '=', $key));
    return $query->fetchSingleModel();
  }

  /**
   * @return string
   */
  public function getSubject() : string
  {
    return $this->subject;
  }

  /**
   * @param string $subject
   */
  public function setSubject(string $subject) : void
  {
    $this->subject = $subject;
    $this->entity->field_subject->value = $subject;
  }

  /**
   * @return string
   */
  public function getHtml() : string
  {
    return $this->html;
  }

  /**
   * @param string $html
   */
  public function setHtml(string $html) : void
  {
    $this->html = $html;
    $this->entity->field_html_body->value = $html;
  }

  /**
   * @return string
   */
  public function getText() : string
  {
    return $this->text;
  }

  /**
   * @param string $text
   */
  public function setText(string $text) : void
  {
    $this->text = $text;
    $this->entity->field_text_body->value = $text;
  }

}
