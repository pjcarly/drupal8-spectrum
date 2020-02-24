<?php

namespace Drupal\spectrum\Config;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class AdminSettingsForm extends ConfigFormBase
{
  public function getFormID()
  {
    return 'spectrum_admin_settings';
  }

  protected function getEditableConfigNames()
  {
    return ['spectrum.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('spectrum.settings');

    $form['default_base_path'] = [
      '#type' => 'textfield',
      '#title' => t('Default Base Path'),
      '#default_value' => $config->get('default_base_path'),
      '#description' => t('The default base path, in case logic is done via the CLI and a base_path is needed, make sure this is the URL your website is accessible on.')
    ];

    $form['email_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Preferred Email Provider'),
      '#options' => [
        '' => '-- None --',
        'sendgrid' => 'SendGrid',
        'aws-ses' => 'AWS SES',
      ],
      '#default_value' => $config->get('email_provider'),
    ];

    $form['aws_ses_api_key'] = [
      '#type' => 'textfield',
      '#title' => t('AWS SES Key'),
      '#default_value' => $config->get('aws_ses_api_key'),
      '#description' => t('The key for your AWS SES account.')
    ];

    $form['aws_ses_api_secret'] = [
      '#type' => 'textfield',
      '#title' => t('AWS SES Secret'),
      '#default_value' => $config->get('aws_ses_api_secret'),
      '#description' => t('The Secret for your AWS SES account.')
    ];

    $form['aws_ses_region'] = [
      '#type' => 'textfield',
      '#title' => t('AWS SES Region'),
      '#default_value' => $config->get('aws_ses_region'),
      '#description' => t('The Region for your AWS SES account.')
    ];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config = $this->config('spectrum.settings');
    $config->set('default_base_path', $form_state->getValue('default_base_path'));
    $config->set('email_provider', $form_state->getValue('email_provider'));
    $config->set('aws_ses_api_key', $form_state->getValue('aws_ses_api_key'));
    $config->set('aws_ses_api_secret', $form_state->getValue('aws_ses_api_secret'));
    $config->set('aws_ses_region', $form_state->getValue('aws_ses_region'));
    $config->save();

    parent::submitForm($form, $form_state);
  }
}
