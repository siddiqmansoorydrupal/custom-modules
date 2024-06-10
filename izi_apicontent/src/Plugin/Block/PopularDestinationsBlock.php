<?php

namespace Drupal\izi_apicontent\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\IziTravel\DataType\FeaturedCityInterface;

/**
 * Provides a popular destinations block.
 *
 * @Block(
 *   id = "izi_apicontent_popular_destinations",
 *   admin_label = @Translation("Popular Destinations"),
 *   category = @Translation("IZI")
 * )
 */
class PopularDestinationsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The izi_apicontent.izi_object_service service.
   *
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected $iziObjectService;

  /**
   * The izi_apicontent.language_service service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected $languageService;

  /**
   * Constructs a new PopularDestinationsBlock instance.
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
   *   The izi_apicontent.izi_object_service service.
   * @param \Drupal\izi_apicontent\LanguageService $language_service
   *   The izi_apicontent.language_service service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, IziObjectService $izi_object_service, LanguageService $language_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->iziObjectService = $izi_object_service;
    $this->languageService = $language_service;
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
      $container->get('izi_apicontent.language_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $featured_content = $this->iziObjectService->izi_apicontent_home_content_load();

    // Select only the featured cities.
    foreach ($featured_content as $featured_item) {
      if ($featured_item instanceof FeaturedCityInterface) {
        // Build the city url.
        $uuid = $featured_item->getUuid();
        try {
          $route_url = $this->iziObjectService->izi_apicontent_drupal_url($uuid, IZI_APICONTENT_TYPE_CITY);
          $cities[] = Link::fromTextAndUrl(
            $this->t(Xss::filter($featured_item->getName())),
            $route_url
          );
        }
        catch (\Exception $e) {
          $this->getLogger('izi_apicontent')
            ->error('Cannot get city %uuid for Popular Destination Block.', [
              '%uuid' => $uuid,
            ]);
        }
      }
    }

    if (!empty($this->configuration['title'])) {
      $build['#title'] = $this->t($this->configuration['title']);
    }
    if (!empty($cities)) {
      $build['#links'] = $cities;
      if (!!empty($this->configuration['title'])) {
        $build['#title'] = $this->t($this->configuration['title']);
      }
    }
    $build['#cache'] = [
      'max-age' => 0,
    ];
    return $build;
  }

}
