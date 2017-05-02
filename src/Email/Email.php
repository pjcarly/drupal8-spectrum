<?php

namespace Drupal\spectrum\Email;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Email\EmailTemplate;

class Email
{
  private $email;
  private $template;

  private $toAddresses = [];
  private $fromAddress = '';
  private $fromName = '';
  private $replyTo = '';

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

  public function setTemplate(EmailTemplate $template)
  {
    $this->template = $template;
  }

  public function send()
  {
    // Get environment variables
    $template = $this->template;
    $template->render();

    // we will now configure SES to send our email
    $config = \Drupal::config('spectrum.settings');
    $emailProvider = $config->get('email_provider');

    // Depending on the Email provider, we do something else
    try
    {
      if($emailProvider === 'sendgrid')
      {
        $sendGridKey = $config->get('sendgrid_api_key');

        if (!strlen($sendGridKey)) {
          \Drupal::logger('spectrum')->error('SendGrid API Key blank.');
          return NULL;
        }

        // we instantiate our client
        $sendgridMessage = new \SendGrid\Email();

        // And set the values
        $sendgridMessage->addTo($this->toAddresses);
        $sendgridMessage->setFrom($this->fromAddress);
        $sendgridMessage->setFromName($this->fromName);
        $sendgridMessage->setReplyTo($this->replyTo);

        // twig already rendered our template, lets set the values
        $sendgridMessage->setSubject($template->subject);
        $sendgridMessage->setText($template->text);
        $sendgridMessage->setHtml($template->html);

        // And finally send the email
        $client = new \SendGrid($sendGridKey);
        $client->send($sendgridMessage);
      }
      else if($emailProvider === 'aws-ses')
      {
        $awsKey = $config->get('aws_ses_api_key');
        $awsSecret = $config->get('aws_ses_api_secret');
        $awsRegion = $config->get('aws_ses_region');

        if (!strlen($awsKey)) {
          \Drupal::logger('spectrum')->error('AWS SES Key blank.');
          return NULL;
        }
        if (!strlen($awsSecret)) {
          \Drupal::logger('spectrum')->error('AWS SES Secret blank.');
          return NULL;
        }
        if (!strlen($awsRegion)) {
          \Drupal::logger('spectrum')->error('AWS SES Region blank.');
          return NULL;
        }

        $client = \Aws\Ses\SesClient::factory([
          'region' => $awsRegion,
          'version' => '2010-12-01',
          'credentials' => [
            'key' => $awsKey,
            'secret' => $awsSecret
          ]
        ]);

        $toAddresses = $this->toAddresses;
        $fromAddress = $this->fromAddress;
        $fromName = $this->fromName;
        $replyTo = $this->replyTo;

        $from = empty($fromName) ? $fromAddress : '"'.$fromName.'" <'.$fromAddress.'>';
        $payload = [];
        $payload['Destination'] = [
          'BccAddresses' => [],
          'CcAddresses' => [],
          'ToAddresses' => $toAddresses
        ];
        $payload['Message'] = [
          'Body' => [
            'Html' => [
              'Charset' => 'UTF-8',
              'Data' => $template->html,
            ],
            'Text' => [
              'Charset' => 'UTF-8',
              'Data' => $template->text,
            ],
          ],
          'Subject' => [
            'Charset' => 'UTF-8',
            'Data' => $template->subject,
          ],
        ];
        $payload['Source'] = $from;

        if(!empty($replyTo))
        {
          $payload['ReplyToAddresses'] = [$replyTo];
        }

        $result = $client->sendEmail($payload);
      }
      else
      {
        return NULL;
      }
    }
    catch(\Aws\Ses\Exception\SesException $exc)
    {
      \Drupal::logger('spectrum')->error('AWS Exception: '.$exc->getMessage());
      return NULL;
    }
    catch(Exception $exc)
    {
      \Drupal::logger('spectrum')->error('Email Sending Exception: '.$exc->getMessage());
      return NULL;
    }
  }
}
