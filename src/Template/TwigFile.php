<?php

namespace Drupal\spectrum\Template;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Model\SimpleConfigWrapper;
use Drupal\spectrum\Template\TwigRenderer;
use Drupal\spectrum\Utils\UrlUtils;

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

    $this->addObjectToScope('rootUrl', UrlUtils::getBaseURL());
  }

  public function addConfigToScope($name, $config)
  {
    $this->scope[$name] = new SimpleConfigWrapper($config);
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
