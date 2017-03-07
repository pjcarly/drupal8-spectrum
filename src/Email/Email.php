<?php

namespace Drupal\spectrum\Email;

use Aws\Ses\SesClient;

class Email
{
  private $email;
  private $template;
  private $toAddresses = [];
  private $fromAddress = '';
  private $fromName = '';
  private $replyTo = '';

  private $templateParameters;

  public function addTo($email)
  {
    $this->toAddresses[] = $email;
  }

  public function setFrom($email)
  {
    $this->fromAddress = $email;
  }

  public function setFromName($name)
  {
    $this->fromName = $name;
  }

  public function setReplyTo($email)
  {
    $this->replyTo = $email;
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

    // $config = \Drupal::config('spectrum.settings');
    //
    // $api_key = $config->get('sendgrid_api_key');
    // if (!strlen($api_key)) {
    //   \Drupal::logger('spectrum')->error('Spectrum Error: API Key cannot be blank.');
    //   return NULL;
    // }


    $client = SesClient::factory([
      'region' => 'eu-west-1',
      'version' => '2010-12-01',
      'credentials' => [
        'key' => '<key>',
        'secret' => '<secret>'
      ]
    ]);

    $subject = $this->template->renderBlock('subject', $this->templateParameters);
    $text = $this->template->renderBlock('body_text', $this->templateParameters);
    $html = $this->template->renderBlock('body_html', $this->templateParameters);

    try {

      $result = $client->sendEmail([
          'Destination' => [
              'BccAddresses' => [],
              'CcAddresses' => [],
              'ToAddresses' => [
                  'pieterjan@carly.be',
              ],
          ],
          'Message' => [
              'Body' => [
                  'Html' => [
                      'Charset' => 'UTF-8',
                      'Data' => $html,
                  ],
                  'Text' => [
                      'Charset' => 'UTF-8',
                      'Data' => $text,
                  ],
              ],
              'Subject' => [
                  'Charset' => 'UTF-8',
                  'Data' => $subject,
              ],
          ],
          'Source' => 'pieterjan@entice.be',
      ]);

		} catch(\Aws\Ses\Exception\SesException $exc) {
			$result	= $exc->getMessage();
		}

    dump($result);
    die;
  }
}
