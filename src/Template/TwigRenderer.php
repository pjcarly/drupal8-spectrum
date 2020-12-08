<?php

namespace Drupal\spectrum\Template;

use Drupal;
use Drupal\Core\Template\TwigEnvironment;

/**
 * This class exposes a render function to render Twig Templates with.
 */
class TwigRenderer
{
  /**
   * The twig rendering engine
   *
   * @var [type]
   */
  private $twig;

  /**
   * Returns the twig renderer that will be used to render twig templates
   *
   * @return object
   */
  private function getTwigRenderer(): TwigEnvironment
  {
    if (empty($this->twig)) {
      // We need to get the twig environment from Drupal as we will use it to render the email template
      // Important to CLONE the twig environment, as any change we make here, shouldn't affect drupal rendering
      $this->twig = clone \Drupal::service('twig');
    }

    return $this->twig;
  }

  /**
   * Parses the passed in twig template with the passed in scope
   *
   * @param string $template
   * @param array $scope
   * @return string
   */
  public function render(string $template, array $scope = []): string
  {
    if (!empty($template)) {
      $twig = $this->getTwigRenderer();
      $template = $twig->createTemplate($template);
      return $template->render($scope);
    }

    return '';
  }
}
