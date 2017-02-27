<?php

namespace Drupal\spectrum\Email;

use Drupal\mist\Models\EmailTemplate;
use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Email\EmailModelWrapper;

class Email
{
  private $email;
  private $template;
  private $templateParameters;
  private $scope = [];

  public function __construct()
  {
    $this->email = new \SendGrid\Email();
  }

  public function addTo($email)
  {
    $this->email->addTo($email);
  }

  public function setFrom($email)
  {
    $this->email->setFrom($email);
  }

  public function setFromName($name)
  {
    $this->email->setFromName($name);
  }

  public function setReplyTo($email)
  {
    $this->email->setReplyTo($email);
  }

  public function setTemplate(EmailTemplate $template)
  {
    $this->template = $template;
  }

  public function addModelToScope($name, Model $model)
  {
    $this->scope[$name] = new EmailModelWrapper($model);
  }

  public function send()
  {
    // First lets make sure we have an API key
    $config = \Drupal::config('spectrum.settings');
    $api_key = $config->get('sendgrid_api_key');
    if (!strlen($api_key)) {
      \Drupal::logger('spectrum')->error('Spectrum Error: API Key cannot be blank.');
      return NULL;
    }

    // Get environment variables
    $template = $this->template;
    $scope = $this->scope;

    // We need to get the twig environment from Drupal as we will use it to render the email template
    // Important to CLONE the twig environment, as any change we make here, shouldn't affect drupal rendering
    $twig = clone \Drupal::service('twig');
    $twig->setLoader(new \Twig_Loader_String());

    // Lets render the different parts of the email template
    $subject = $twig->render($template->entity->field_subject->value, $scope);
    $html = $twig->render($template->entity->field_html_body->value, $scope);
    $text = $twig->render($template->entity->field_text_body->value, $scope);

    // Now that twig rendered our templates, we can use those values to set Sendgrid
    $this->email->setSubject($subject);
    $this->email->setText($text);
    $this->email->setHtml($html);

    // And finally send the email
    $sendgrid = new \SendGrid($api_key);
    $sendgrid->send($this->email);
  }
}
