<?php

namespace Drupal\spectrum\Template;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Model\SimpleModelWrapper;
use Drupal\spectrum\Email\EmailTemplate;

class TwigRenderer
{
  private $twig;

  private function getTwigRenderer()
  {
    if(empty($this->twig))
    {
      // We need to get the twig environment from Drupal as we will use it to render the email template
      // Important to CLONE the twig environment, as any change we make here, shouldn't affect drupal rendering
      $this->twig = clone \Drupal::service('twig');
      $this->twig->setLoader(new \Twig_Loader_String());
    }
    return $this->twig;
  }

  public function render(string $template, array $scope = [])
  {
    if(!empty($template))
    {
      $twig = $this->getTwigRenderer();
      $renderedContent = $twig->loadTemplate($template)->render($scope);
      return $renderedContent;
    }
    return '';
  }
}
