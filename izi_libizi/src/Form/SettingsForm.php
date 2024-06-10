<?php

namespace Drupal\izi_libizi\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\izi_libizi\Libizi;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure IZI Libizi settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The Drupal State API service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The Libizi service.
   *
   * @var \Drupal\izi_libizi\Libizi
   */
  protected Libizi $libizi;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The Drupal State API service.
   * @param \Drupal\izi_libizi\Libizi $libizi
   *   The Libizi service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    StateInterface $state,
    Libizi $libizi
  ) {
    parent::__construct($config_factory);
    $this->libizi = $libizi;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('izi_libizi.libizi')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_libizi_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['izi_libizi.settings'];
  }

  /**
   *
   */
  private function buildApiStatusMessage() {
    $status = $this->libizi->getApiStatus();

    $css_class = $status['code'] < 300 ? 'messages--status' : 'messages--warning';
    return [
      '#theme' => 'container',
      '#attributes' => [
        'class' => ['messages', $css_class],
      ],
      '#children' => [
        ['#markup' => '<p>' . $this->t('Current environment:<b> %environment </b>', ['%environment' => $status['environment']]) . '</p>'],
        ['#markup' => '<p>' . $this->t('HTTP Status:<b> %status </b>', ['%status' => $status['code']]) . '</p>'],
        ['#markup' => '<p>' . $this->t('Message:<b> %message </b>', ['%message' => $status['message']]) . '</p>'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['api_status_message'] = $this->buildApiStatusMessage();

    $form['izi_api_key_prod'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IZI API key Prod'),
      '#default_value' => $this->state->get('izi_libizi:api_key_prod'),
      '#required' => TRUE,
    ];

    $form['izi_api_key_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IZI API key Test'),
      '#default_value' => $this->state->get('izi_libizi:api_key_test'),
    ];

    $form['izi_api_key_stage'] = [
      '#type' => 'textfield',
      '#title' => $this->t('IZI API key Stage'),
      '#default_value' => $this->state->get('izi_libizi:api_key_stage'),
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
    $this->state->set('izi_libizi:api_key_prod', $form_state->getValue('izi_api_key_prod'));
    $this->state->set('izi_libizi:api_key_test', $form_state->getValue('izi_api_key_test'));
    $this->state->set('izi_libizi:api_key_stage', $form_state->getValue('izi_api_key_stage'));

    parent::submitForm($form, $form_state);
  }

}
