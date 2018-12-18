<?php

namespace Drupal\spectrum\Template;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Query\ModelQuery;
use Drupal\spectrum\Query\Condition;

use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Model\SimpleConfigWrapper;
use Drupal\spectrum\Template\TwigRenderer;
use Drupal\spectrum\Utils\UrlUtils;

use Drupal\Core\Config\ImmutableConfig;

/**
 * This class is used to render a twig file
 */
class TwigFile
{
  /**
   * the array containing all the objects that will be added to the scope of the twig file
   * They key in the array will be the key you can access the object in your twig file {{ key.object }}
   *
   * @var array
   */
  private $scope = [];

  /**
   * The content of the Twig file, unrendered
   *
   * @var string
   */
  private $fileContent;

  /**
   * The rendered HTML
   *
   * @var string
   */
  public $html = '';

  /**
   * @param string $path the path to your twigfile
   */
  public function __construct(string $path)
  {

    if (!file_exists($path)) {
      $exception = strtr('File \'@file\' does not exist.', [
        '@file' => $path,
      ]);
      throw new \InvalidArgumentException($exception);
    }

    $fileContent = file_get_contents($path);
    if($fileContent)
    {
      $this->fileContent = $fileContent;
    }

    $this->addObjectToScope('rootUrl', UrlUtils::getBaseURL());
  }

  /**
   * Adds a drupal Config object to the scope of the template
   *
   * @param string $name
   * @param ImmutableConfig $config
   * @return void
   */
  public function addConfigToScope(string $name, ImmutableConfig $config) : TwigFile
  {
    $this->scope[$name] = new SimpleConfigWrapper($config);

    return $this;
  }

  /**
   * Adds a Model to the scope of the twig file. The model will be wrapped in a SimpleModelWrapper
   * So you can access model values without knowing the drupal implementation, based on the jsonapi.org pretty fields
   * For example {{ user.first_name }} instead of {{ user.entity.field_first_name.value }}
   *
   * @param string $name
   * @param Model $model
   * @return TwigFile
   */
  public function addModelToScope(string $name, Model $model) : TwigFile
  {
    $this->scope[$name] = new SimpleModelWrapper($model);

    return $this;
  }

  /**
   * Add any type of object to the scope of the TwigFile
   *
   * @param string $name
   * @param mixed $object
   * @return TwigFile
   */
  public function addObjectToScope(string $name, $object) : TwigFile
  {
    $this->scope[$name] = $object;

    return $this;
  }

  /**
   * Renders the twigfile, the content of the render will be stored in the $html property on this object
   *
   * @return TwigFile
   */
  public function render() : TwigFile
  {
    // Use the spectrum twigrenderer;
    $twig = new TwigRenderer();

    // Lets render the different parts of the email template
    $this->html = $twig->render($this->fileContent, $this->scope);

    return $this;
  }
}
