<?php

namespace Drupal\spectrum\Template;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Template\TwigRenderer;

class TwigFile
{
  private $scope = [];
  private $fileContent;
  public $html = '';

  public function __construct(string $path)
  {
    $fileContent = file_get_contents($path);
    if($fileContent)
    {
      $this->fileContent = $fileContent;
    }

    $request = \Drupal::request();
    $rootUrl = $request->getSchemeAndHttpHost() . base_path();

    $this->addObjectToScope('rootUrl', $rootUrl);
  }

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
    $this->html = $twig->render($this->fileContent, $this->scope);
  }
}
