<?php

namespace Drupal\izi_urlalias\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure IZI Url Alias settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_urlalias_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['izi_urlalias.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['delete_threshold'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delete Threshold (seconds).'),
      '#description' => $this->t('The time in seconds before old items and their aliases will deleted permanently.'),
      '#default_value' => $this->config('izi_urlalias.settings')->get('delete_threshold'),
    ];
    $form['delete_batch_limit'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delete Batch Limit.'),
      '#description' => $this->t('The max number of items that will be deleted in a single cron run.'),
      '#default_value' => $this->config('izi_urlalias.settings')->get('delete_batch_limit'),
    ];

    $form['cron_threshold'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Delete Cron Threshold'),
      '#description' => $this->t('The minimum time (in seconds) between which deletion cron will occur.'),
      '#default_value' => $this->config('izi_urlalias.settings')->get('cron_threshold'),
    ];
    $form['debug_sitemap'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug Sitemap'),
      '#default_value' => $this->config('izi_urlalias.settings')->get('debug_sitemap'),
    ];
    $form['debug_aliases'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug Aliases'),
      '#default_value' => $this->config('izi_urlalias.settings')->get('debug_aliases'),
    ];
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
    $this->config('izi_urlalias.settings')
      ->set('delete_threshold', $form_state->getValue('delete_threshold'))
      ->set('delete_batch_limit', $form_state->getValue('delete_batch_limit'))
      ->set('cron_threshold', $form_state->getValue('cron_threshold'))
      ->set('debug_sitemap', $form_state->getValue('debug_sitemap'))
      ->set('debug_aliases', $form_state->getValue('debug_aliases'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
