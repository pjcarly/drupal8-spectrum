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

    $form['sendgrid_api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Sendgrid API Key'),
      '#default_value' => $config->get('sendgrid_api_key'),
      '#description' => t('The API key for your Sendgrid account.')
    );

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $config = $this->config('spectrum.settings');
    $config->set('sendgrid_api_key', $form_state->getValue('sendgrid_api_key'));
    $config->save();

    parent::submitForm($form, $form_state);
  }
}
