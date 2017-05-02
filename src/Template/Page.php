<?php

namespace Drupal\spectrum\Template;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Template\TwigRenderer;

class Page extends Model
{
	public static $entityType = 'template';
	public static $bundle = 'page';
	public static $idField = 'id';
  public static $plural = 'Page Templates';

  private $scope = [];
  public $html = '';
	/* TRIGGERS */


	/* TRIGGER METHODS */


	/* BUSINESS LOGIC */
  public function addModelToScope($name, Model $model)
  {
    $this->scope[$name] = new SimpleModelWrapper($model);
  }

  public function render()
  {
    // Use the spectrum twigrenderer;
    $twig = new TwigRenderer();

    // Lets render the different parts of the email template
    $this->html = $twig->render($this->entity->field_html_body->value, $this->scope);
  }

  public static function getByKey($name)
  {
    $query = new ModelQuery('Drupal\spectrum\Template\Page');
    $query->addCondition(new Condition('field_key', '=', $name));
    return $query->fetchSingleModel();
  }
}
