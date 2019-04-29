<?php

namespace Drupal\spectrum\Email;

use Drupal\spectrum\Model\Collection;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\SimpleCollectionWrapper;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Template\TwigRenderer;
use Drupal\spectrum\Model\FieldRelationship;

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

  public static function relationships()
  {
    parent::relationships();
    static::addRelationship(new FieldRelationship('base_template', 'field_base_template.target_id'));
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
    $baseTemplate = $this->fetchBaseTemplateIfNeeded();

    $subject = $this->getSubject() ?? '';
    $html = $this->getHtml() ?? '';
    $text = $this->getText() ?? '';

    if(!empty($baseTemplate))
    {
      $this->addObjectToScope('baseTemplate', $baseTemplate->getHtml() ?? '');
      $html = '{% extends baseTemplate %}' . $html;
    }

    // Use the spectrum twigrenderer;
    $twig = new TwigRenderer();

    // Lets render the different parts of the email template
    $this->subject = $twig->render($subject, $this->scope);
    $this->html = $twig->render($html, $this->scope);
    $this->text = $twig->render($text, $this->scope);

    return $this;
  }

  public function fetchBaseTemplateIfNeeded() : ?BaseTemplate
  {
    if(!empty($this->getBaseTemplateId()))
    {
      $baseTemplate = $this->getBaseTemplate();

      if(empty($baseTemplate))
      {
        $baseTemplate = $this->fetch('base_template');
      }

      return $baseTemplate;
    }

    return null;
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
  public function getSubject() : ?string
  {
    return $this->entity->field_subject->value;
  }

  /**
   * @param string $subject
   */
  public function setSubject(string $subject) : EmailTemplate
  {
    $this->entity->field_subject->value = $subject;

    return $this;
  }

  /**
   * @return string
   */
  public function getHtml() : ?string
  {
    return $this->entity->field_html_body->value;
  }

  /**
   * @param string $html
   */
  public function setHtml(string $html) : EmailTemplate
  {
    $this->entity->field_html_body->value = $html;

    return $this;
  }

  /**
   * @return string
   */
  public function getText() : ?string
  {
    return $this->entity->field_text_body->value;
  }

  /**
   * @param string $text
   */
  public function setText(string $text) : EmailTemplate
  {
    $this->entity->field_text_body->value = $text;
    return $this;
  }

  /**
   * @return string
   */
  public function getKey() : ?string
  {
    return $this->entity->field_key->value;
  }

  /**
   * @param string $text
   */
  public function setKey(string $text) : EmailTemplate
  {
    $this->entity->field_key->value = $text;
    return $this;
  }

  /**
   * @return string
   */
  public function getBaseTemplate() : ?BaseTemplate
  {
    return $this->get('base_template');
  }

  /**
   * @param string $text
   */
  public function setBaseTemplate(BaseTemplate $baseTemplate) : EmailTemplate
  {
    $this->put('base_template', $baseTemplate);

    return $this;
  }

  /**
   * @return string
   */
  public function getBaseTemplateId() : ?int
  {
    return $this->entity->field_base_template->target_id;
  }

  /**
   * @param string $text
   */
  public function setBaseTemplateId(int $id) : EmailTemplate
  {
    $this->entity->field_base_template->target_id = $id;

    return $this;
  }

  /**
   * Returns the rendered HTML. Make sure to call render() before calling this function.
   *
   * @return string
   */
  public function getRenderedHtml() : ?string
  {
    return $this->html;
  }

  /**
   * Returns the rendered Text. Make sure to call render() before calling this function.
   *
   * @return string
   */
  public function getRenderedText() : ?string
  {
    return $this->text;
  }

  /**
   * Returns the rendered Subject. Make sure to call render() before calling this function.
   *
   * @return string
   */
  public function getRenderedSubject() : ?string
  {
    return $this->subject;
  }
}
