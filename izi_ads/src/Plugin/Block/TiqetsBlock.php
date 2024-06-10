<?php

namespace Drupal\izi_ads\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a tiqets block.
 *
 * @Block(
 *   id = "izi_ads_tiqets",
 *   admin_label = @Translation("Tiqets"),
 *   category = @Translation("IZI")
 * )
 */
class TiqetsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Service current_route_match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  private $currentRouteMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The izi_apicontent.language_service service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected $languageService;

  /**
   * The izi_apicontent.izi_object_service service.
   *
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected $iziObjectService;

  /**
   * Constructs a new TiqetsBlock instance.
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
   * @param \Drupal\Core\Routing\CurrentRouteMatch $currentRouteMatch
   *   Current route.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\izi_apicontent\LanguageService $language_service
   *   The izi_apicontent.language_service service.
   * @param \Drupal\izi_apicontent\IziObjectService $izi_object_service
   *   The izi_apicontent.izi_object_service service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $currentRouteMatch, RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager, LanguageService $language_service, IziObjectService $izi_object_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentRouteMatch = $currentRouteMatch;
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->languageService = $language_service;
    $this->iziObjectService = $izi_object_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('izi_apicontent.language_service'),
      $container->get('izi_apicontent.izi_object_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Get current IZI UUID.
    $uuid = $this->iziObjectService->getCurrentPageUuid();
    $request = $this->requestStack->getCurrentRequest();
    $route = $this->currentRouteMatch->getCurrentRouteMatch();
    $parameters = $route->getRawParameters()->all();

    if (!$uuid && !empty($parameters['city'])) {
      $uuid = $parameters['city'];
    }

    $nodes = [];
    if ($uuid) {
      $nodes = $this->entityTypeManager
        ->getStorage('node')
        ->loadByProperties([
          'field_uuid' => $uuid,
        ]);
    }

    if (count($nodes)) {
      /** @var \Drupal\node\Entity\Node $node */
      $node = reset($nodes);

      $partner = $node->get('field_partner')->first()->getValue()['value'];
      $campaign = $node->get('field_campaign_id')->first()->getValue()['value'];
      $city_id = $node->get('field_city_id')->first()->getValue()['value'];
      $show_widget = $node->get('field_show_widget')->first()->getValue()['value'];
      $lang = $this->languageService->get_interface_language();

      $build['content'] = [
        '#theme' => 'izi_tiqets',
        '#language' => $lang,
        '#partner' => $partner,
        '#campaign' => $campaign,
        '#city' => $city_id,
        '#show_widget' => $show_widget,
      ];
      if ($show_widget) {
        $build['#attached']['library'][] = 'izi_ads/izi_ads.tiqets_loader';
      }
    }
    return $build;
  }

}
