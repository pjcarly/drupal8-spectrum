<?php

namespace Drupal\spectrum\Email;

class Email
{
  private $email;
  private $template;
  private $templateParameters;

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

  public function setTemplate($templateName)
  {
    $twig = \Drupal::service('twig');
    $this->template = $twig->loadTemplate($templateName);
  }

  public function setTemplateParameters($parameters)
  {
    $this->templateParameters = $parameters;
  }

  public function send()
  {
    $this->email->setSubject($this->template->renderBlock('subject', $this->templateParameters));
    $this->email->setText($this->template->renderBlock('body_text', $this->templateParameters));
    $this->email->setHtml($this->template->renderBlock('body_html', $this->templateParameters));

    // TODO get API Key from config
    $sendgrid = new \SendGrid('API_KEY');
    $sendgrid->send($this->email);
  }
}
