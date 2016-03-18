<?php
/**
 * @file
 * Contains \Drupal\spectrum\Form\SpectrumAdminSettingsForm.
 */

namespace Drupal\spectrum\Config;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Configure Spectrum settings for this site.
 */
class AdminSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'spectrum_admin_settings';
  }

  protected function getEditableConfigNames() {
    return ['spectrum.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('spectrum.settings');

    $form['sendgrid_api_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Sendgrid API Key'),
      '#required' => TRUE,
      '#default_value' => $config->get('sendgrid_api_key'),
      '#description' => t('The API key for your Sendgrid account.')
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('spectrum.settings');
    $config
      ->set('sendgrid_api_key', $form_state->getValue('sendgrid_api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
