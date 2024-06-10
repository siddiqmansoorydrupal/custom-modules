<?php

namespace Drupal\izi_context\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a 'IziContext' condition.
 *
 * @Condition(
 *   id = "izi_context",
 *   label = @Translation("IziContext"),
 * )
 *
 * @DCG prior to Drupal 8.7 the 'context_definitions' key was called 'context'.
 */
class IziContext extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Izi object service.
   *
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected IziObjectService $iziObjects;

  /**
   * Creates a new IziContext instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\izi_apicontent\IziObjectService $izi_object_service
   *   The izi ojbect service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    IziObjectService $izi_object_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->iziObjects = $izi_object_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('izi_apicontent.izi_object_service'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['subtypes' => []] + parent::defaultConfiguration();
  }

  /**
   *
   */
  protected function getOptions() {
    $subtypes = $this->iziObjects->izi_apicontent_get_sub_types();
    return array_combine($subtypes, $subtypes);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $default_value = $this->configuration['subtypes'];

    $form['subtypes'] = [
      '#type' => 'checkboxes',
      '#title' => t('Subtypes of IZI Objects'),
      '#options' => $this->getOptions(),
      '#default_value' => $default_value,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $value = $form_state->getValue('subtypes');
    $this->configuration['subtypes'] = array_keys(array_filter($value));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t(
      'Subtypes: @subtypes',
      ['@subtypes' => implode(', ', $this->configuration['subtypes'])]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (!$this->configuration['subtypes'] && !$this->isNegated()) {
      return TRUE;
    }
    if ($uuid = $this->iziObjects->getCurrentPageUuid()) {
      try {
        $object = $this->iziObjects->loadObjectByUUID($uuid, IZI_APICONTENT_TYPE_MTG_OBJECT);
        if ($object) {
          $subtype = $this->iziObjects->izi_apicontent_get_sub_type($object);
          $selected_subtypes = $this->configuration['subtypes'];
          return in_array($subtype, $selected_subtypes);
        }
      }
      catch (IziLibiziNotFoundException $e) {
        throw new NotFoundHttpException();
      }
    }

    return FALSE;
  }

}
