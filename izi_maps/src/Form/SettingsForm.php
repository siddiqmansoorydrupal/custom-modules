<?php

namespace Drupal\izi_maps\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure izi_maps settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The Drupal State API service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal State API service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_maps_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['izi_maps.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['google_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Google Maps api key'),
      '#default_value' => $this->state->get('izi_maps:google_api_key'),
      '#required' => TRUE,
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
    $this->config('izi_maps.settings')
      ->set('example', $form_state->getValue('example'))
      ->save();
    $this->state
      ->set('izi_maps:google_api_key', $form_state->getValue('google_api_key'));
    parent::submitForm($form, $form_state);
  }

}
