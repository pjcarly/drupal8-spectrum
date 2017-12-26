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
  /* TRIGGERS */


  /* TRIGGER METHODS */


  /* BUSINESS LOGIC */
  public function addModelToScope($name, Model $model)
  {
    $this->scope[$name] = new SimpleModelWrapper($model);
  }

  public function addObjectToScope($name, $object)
  {
    $this->scope[$name] = $object;
  }

  public function render()
  {
    // Use the spectrum twigrenderer;
    $twig = new TwigRenderer();

    // Lets render the different parts of the email template
    $this->subject = $twig->render($this->entity->field_subject->value, $this->scope);
    $this->html = $twig->render($this->entity->field_html_body->value, $this->scope);
    $this->text = $twig->render($this->entity->field_text_body->value, $this->scope);
  }

  public static function getByKey($name)
  {
    $query = new ModelQuery('Drupal\spectrum\Email\EmailTemplate');
    $query->addCondition(new Condition('field_key', '=', $name));
    return $query->fetchSingleModel();
  }
}
