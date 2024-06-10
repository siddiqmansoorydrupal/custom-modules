<?php

namespace Drupal\izi_cognito\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Izi cognito settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_cognito_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['izi_cognito.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['signup_terms_confirm'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Signup Terms & Conditions'),
      '#default_value' => $this->config('izi_cognito.settings')->get('signup_terms_confirm'),
    ];
	 $form['login_terms_confirm'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Login Terms & Conditions'),
      '#default_value' => $this->config('izi_cognito.settings')->get('login_terms_confirm'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // if ($form_state->getValue('signup_terms_confirm') != 'signup_terms_confirm') {
    //   $form_state->setErrorByName('signup_terms_confirm', $this->t('The value is not correct.'));
    // }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('izi_cognito.settings')
      ->set('signup_terms_confirm', $form_state->getValue('signup_terms_confirm'))
      ->set('login_terms_confirm', $form_state->getValue('login_terms_confirm'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
