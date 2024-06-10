<?php

namespace Drupal\menu_join\Plugin\ContextReaction;

use Drupal\context\ContextInterface;
use Drupal\context\ContextReactionPluginBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a reaction that sets the active trail and extended breadcrumbs.
 *
 * @ContextReaction(
 *   id = "menu_join",
 *   label = @Translation("Menu Join")
 * )
 */
class MenuJoinActiveTrail extends ContextReactionPluginBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, TranslationInterface $stringTranslation) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->stringTranslation = $stringTranslation;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('Lets you join breadcrumbs for multiple active trails.');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state, ContextInterface $context = NULL) {

    $form['description'] = [
      '#markup' => $this->getStringTranslation()->translate('<p>Create breadcrumbs concatenating active trails from different menus. Each menu should have a separate active context with Active Trail &amp; Multiple Active Trail reactions.</p>'),
    ];

    $entityTypeManager = \Drupal::getContainer()->get('entity_type.manager');
    $menus = $entityTypeManager->getStorage('menu')->loadMultiple();

    $options = [];
    foreach ($menus as $menu_name => $menu) {
      $options[$menu_name] = $menu->label();
    }

    $parent_options = [
      '' => $this->getStringTranslation()->translate('Choose a parent menu.'),
    ];

    $form['menu_0'] = [
      '#type' => 'select',
      '#options' => $parent_options + $options,
      '#default_value' => $this->configuration['menu_0'],
      '#description' => $this->getStringTranslation()->translate(
        '<p>Parent menu item. (eg main)</p>'
      ),
    ];

    $child_options = [
      '' => $this->getStringTranslation()->translate('Choose a submenu.'),
    ];

    $form['menu_1'] = [
      '#type' => 'select',
      '#options' => $child_options + $options,
      '#default_value' => $this->configuration['menu_1'],
      '#description' => $this->getStringTranslation()->translate(
        '<p>Submenu. For now we are limited to parent & submenu.</p>'
      ),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    Cache::invalidateTags(['context_active_trail']);
    $this->setConfiguration($form_state->getValues());
  }

}
