<?php

namespace Drupal\izi_apicontent\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure IZI TRAVEL API content settings for this site.
 */
class AppSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_apicontent_app_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['izi_apicontent.app_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['description'] = [
      '#markup' => $this->t('Links for downloading mobile applications.'),
    ];

    $form['app_download_page'] = [
      '#type' => 'textfield',
      '#requried' => TRUE,
      '#title' => $this->t('Internal App download page. eg.<code>/node/74</code>'),
      '#default_value' => $this->config('izi_apicontent.app_settings')->get('app_download_page'),
    ];

    $form['app_download_apple'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Apple IOS download link'),
      '#default_value' => $this->config('izi_apicontent.app_settings')->get('app_download_apple'),
    ];
    $form['app_download_windows'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Windows download link'),
      '#default_value' => $this->config('izi_apicontent.app_settings')->get('app_download_windows'),
    ];
    $form['app_download_android'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Android download link'),
      '#default_value' => $this->config('izi_apicontent.app_settings')->get('app_download_android'),
    ];

    $form['quest_app_dl_message'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Quest App Download Message'),
      '#default_value' => $this->config('izi_apicontent.app_settings')->get('quest_app_dl_message'),
      '#format' => 'basic_html',
      '#allowed_formats' => ['basic_html'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $download_page_path = $form_state->getValue('app_download_page');
    $url = \Drupal::service('path.validator')->getUrlIfValid($download_page_path);
    if (!$url) {
      $form_state->setErrorByName('app_download_page', $this->t('Must be a valid drupal path.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('izi_apicontent.app_settings')
      ->set('app_download_page', $form_state->getValue('app_download_page'))
      ->save();
    $this->config('izi_apicontent.app_settings')
      ->set('app_download_apple', $form_state->getValue('app_download_apple'))
      ->save();
    $this->config('izi_apicontent.app_settings')
      ->set('app_download_windows', $form_state->getValue('app_download_windows'))
      ->save();
    $this->config('izi_apicontent.app_settings')
      ->set('app_download_android', $form_state->getValue('app_download_android'))
      ->save();

    $val = $form_state->getValue('quest_app_dl_message');
    $this->config('izi_apicontent.app_settings')
      ->set('quest_app_dl_message', $val['value'])
      ->save();
    parent::submitForm($form, $form_state);
  }

}
