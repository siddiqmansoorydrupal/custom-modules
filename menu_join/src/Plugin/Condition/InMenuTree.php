<?php

namespace Drupal\menu_join\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'In Menu Tree' condition compatible with ContextActiveTrail.
 *
 * @Condition(
 *   id = "in_menu_tree",
 *   label = @Translation("In Menu Tree"),
 * )
 */
class InMenuTree extends ConditionPluginBase implements ContainerFactoryPluginInterface {


  /**
   * The Route Matcher.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The MenuLinkManager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface
   */
  protected $menuLinkManager;

  /**
   * Constructs an InMenuTree condition plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route matcher..
   * @param \Drupal\Core\Menu\MenuLinkManagerInterface $menu_link_manager
   *   Link manager..
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    array $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match,
    MenuLinkManagerInterface $menu_link_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->routeMatch = $route_match;
    $this->menuLinkManager = $menu_link_manager;
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
      $container->get('current_route_match'),
      $container->get('plugin.manager.menu.link')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $menus = $this->entityTypeManager->getStorage('menu')->loadMultiple();

    $options = [];
    foreach ($menus as $menu) {
      $options[$menu->id()] = $menu->label();
    }

    $form['in_menu_tree'] = [
      '#title' => $this->t('Available menus'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $this->configuration['in_menu_tree'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['in_menu_tree'] = array_filter($form_state->getValue('in_menu_tree'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if (empty($this->configuration['in_menu_tree'])) {
      return $this->t('No menus are specified');
    }

    if (count($this->configuration['in_menu_tree']) > 1) {
      $options = $this->configuration['in_menu_tree'];
      $options_text = implode(', ', $options);
      if ($this->isNegated()) {
        return $this->t(
          'The current page in the following menu trees @menus',
          ['@menus' => $options_text]
        );
      }

      return $this->t(
        'The item is in the following menu trees @menus',
        ['@menus' => $options_text]
      );
    }

    $menu = reset($this->configuration['in_menu_tree']);

    if ($this->isNegated()) {
      return $this->t('The current page is not in the menu tree @menu', ['@menu' => $menu]);
    }

    return $this->t('The current page is in the menu tree @menu', ['@menu' => $menu]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {

    $menus = $this->configuration['in_menu_tree'];

    if (empty($menus) && !$this->isNegated()) {
      return TRUE;
    }

    /** @var \Drupal\Core\Menu\MenuLinkManagerInterface $mlm */
    $mlm = \Drupal::service('plugin.manager.menu.link');
    /** @var \Drupal\Core\Routing\RouteMatch $routeMatch */
    $routeMatch = \Drupal::service('current_route_match');
    $route_name = $routeMatch->getRouteName();
    $route_parameters = $routeMatch->getRawParameters()->all();

    foreach ($menus as $menu_name) {
      $links = $mlm->loadLinksByRoute(
        $route_name,
        $route_parameters,
        $menu_name
      );
      if (!empty($links)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['in_menu_tree' => []] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    $contexts[] = 'url.path';
    return $contexts;
  }

}
