<?php

namespace Drupal\spectrum\Plugin\Mail;

use Aws\Ses\Exception\SesException;
use Aws\Ses\SesClient;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Mail(
 *   id = "SpectrumMailSystem",
 *   label = @Translation("Spectrum Mailer"),
 *   description = @Translation("Sends the message, using Spectrum.")
 * )
 */
final class MailSystem implements MailInterface, ContainerFactoryPluginInterface {

  private SesClient $client;

  private LoggerInterface $logger;

  public function __construct(
    ConfigFactoryInterface $configFactory,
    LoggerInterface $logger
  ) {
    $config = $configFactory->get('spectrum.settings');
    $this->logger = $logger;

    $this->client = new SesClient([
      'region' => $config->get('aws_ses_region'),
      'version' => '2010-12-01',
      'credentials' => [
        'key' => $config->get('aws_ses_api_key'),
        'secret' => $config->get('aws_ses_api_secret'),
      ],
    ]);
  }

  /**
   * @inheritDoc
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $container->get('config.factory'),
      $container->get('logger.channel.spectrum')
    );
  }

  public function format(array $message) {
    $message['body'] = implode("\n\n", $message['body']);

    return $message;
  }

  public function mail(array $message) {
    $payload['Destination'] = [
      'BccAddresses' => [],
      'CcAddresses' => [],
      'ToAddresses' => [$message['to'] ?? Settings::get('spectrum_email_to')],
    ];

    $payload['Message'] = [
      'Body' => [
        'Html' => [
          'Charset' => 'UTF-8',
          'Data' => $message['body'],
        ],
        'Text' => [
          'Charset' => 'UTF-8',
          'Data' => MailFormatHelper::htmlToText(MailFormatHelper::wrapMail($message['body'])),
        ],
      ],
      'Subject' => [
        'Charset' => 'UTF-8',
        'Data' => $message['subject'],
      ],
    ];

    $payload['Source'] = $message['from'];

    try {
      $this->client->sendEmail($payload);
    } catch (SesException $e) {
      $this->logger->error($e);
    }
  }

}
