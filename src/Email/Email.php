<?php

namespace Drupal\spectrum\Email;

use Drupal\spectrum\Model\Model;
use Drupal\spectrum\Email\EmailTemplate;
use Drupal\spectrum\Exceptions\EmailException;

class Email
{
  /**
   * The template that will be used to send this email with
   *
   * @var string
   */
  private $template;

  /**
   * A list of email Addresses that will be sent to
   *
   * @var array
   */
  private $toAddresses = [];

  /**
   * THe FROM email address
   *
   * @var string
   */
  private $fromAddress = '';

  /**
   * THe FROM name
   *
   * @var string
   */
  private $fromName = '';

  /**
   * The reply to email address
   *
   * @var string
   */
  private $replyTo = '';

  /**
   * Add a recipient
   *
   * @param string $email
   * @return Email
   */
  public function addTo(string $email) : Email
  {
    $this->toAddresses[] = $email;
    return $this;
  }

  /**
   * Set the "from" email address where the email will be sent from
   *
   * @param string $email
   * @return Email
   */
  public function setFrom(string $email) : Email
  {
    $this->fromAddress = $email;
    return $this;
  }

  /**
   * Set the "from" name appearing in the email client of the recipients
   *
   * @param string $name
   * @return Email
   */
  public function setFromName(string $name) : Email
  {
    $this->fromName = $name;
    return $this;
  }

  /**
   * Set the reply to email
   *
   * @param string $email
   * @return Email
   */
  public function setReplyTo(string $email) : Email
  {
    $this->replyTo = $email;
    return $this;
  }

  /**
   * Set an email template that is going to be parsed and sent
   *
   * @param EmailTemplate $template
   * @return Email
   */
  public function setTemplate(EmailTemplate $template) : Email
  {
    $this->template = $template;
    return $this;
  }

  /**
   * Send the email
   *
   * @return Email
   */
  public function send() : Email
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

        if (empty($sendGridKey))
        {
          throw new EmailException('SendGrid API Key blank.');
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

        if (empty($awsKey))
        {
          throw new EmailException('AWS SES Key blank.');
        }

        if (empty($awsSecret))
        {
          throw new EmailException('AWS SES Secret blank.');
        }

        if (empty($awsRegion))
        {
          throw new EmailException('AWS SES Region blank.');
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
        throw new EmailException('No email provider Selected');
      }
    }
    catch(\Aws\Ses\Exception\SesException $exc)
    {
      throw new EmailException('AWS Exception: '.$exc->getMessage());
    }

    return $this;
  }
}
