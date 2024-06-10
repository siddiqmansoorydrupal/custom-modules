<?php

namespace Drupal\izi_apicontent\Plugin\Block;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\izi_apicontent\HelpersService;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\IziTravel\DataType\FeaturedMtgObjectInterface;

/**
 * Provides a featured tours block.
 *
 * @Block(
 *   id = "izi_apicontent_featured_tours",
 *   admin_label = @Translation("Featured Tours"),
 *   category = @Translation("IZI")
 * )
 */
class FeaturedToursBlock extends BlockBase implements ContainerFactoryPluginInterface {

  const NUMBER_OF_ITEMS = 6;
  // 1 day.
  const CACHE_TIME = 86400;
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
   * The izi_apicontent.helpers_service service.
   *
   * @var \Drupal\izi_apicontent\HelpersService
   */
  protected $helpersService;

  /**
   * Constructs a new FeaturedToursBlock instance.
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
   * @param \Drupal\izi_apicontent\HelpersService $helpers_service
   *   The izi_apicontent.helpers_service service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, IziObjectService $izi_object_service, LanguageService $language_service, HelpersService $helpers_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->iziObjectService = $izi_object_service;
    $this->languageService = $language_service;
    $this->helpersService = $helpers_service;
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
      $container->get('izi_apicontent.language_service'),
      $container->get('izi_apicontent.helpers_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $number_of_items = self::NUMBER_OF_ITEMS;
    $langcode = \Drupal::service('izi_apicontent.language_service')
      ->get_interface_language();

    $cid = 'featured_content_' . $number_of_items . '_' . $langcode;
    $items = [];

    if (empty($items)) {
      $featured_content = $this->iziObjectService->izi_apicontent_home_content_load();

      $items = [];
      $positioned_content = [];
      $fallback_content = [];

      /** @var \Triquanta\IziTravel\DataType\FeaturedContentInterface $featured_item */
      foreach ($featured_content as $featured_item) {
        $position = $featured_item->getPosition();
        if ($featured_item instanceof FeaturedMtgObjectInterface) {
          if ($position) {
            $positioned_content[$position] = $featured_item;
          }
          else {
            $fallback_content[] = $featured_item;
          }
        }
      }
      // Sort by position. Note that this position may not be the position an object
      // gets in the final layout, because of the fallback position matrix.
      ksort($positioned_content);

      // Decide which positions get fallback content.
      $fallback_positions = $this->_izi_apicontent_get_home_fallback_positions(count($positioned_content));

      $position = 1;
      while ($position <= $number_of_items) {
        // Check if this position should be filled with fallback content.
        if (in_array($position, $fallback_positions)) {
          // Find the best available fallback content.
          if (count($fallback_content)) {
            // Use an MTG object as fallback content.
            $items[$position] = $this->iziObjectService->izi_apicontent_build_featured_mtg_object(array_shift($fallback_content), $position);
          }
        }
        else {
          $items[$position] = $this->iziObjectService->izi_apicontent_build_featured_mtg_object(array_shift($positioned_content), $position);
        }
        $position++;
      }
    }

    if (!empty($this->configuration['title'])) {
      $build['#title'] = $this->t($this->configuration['title']);
    }

    $build['content'] = $items;
    return $build;
  }

  /**
   * The fallback position matrix defines which positions should be filled with
   * fallback content in case the number of selected content items is less than
   * the number of positions. The positions are numbered as follows:
   *   +---+---+---+            +---+---+---+      +---+---+---+
   *   | 1 | 2 | 3 |    or    < | 1 | 2 | 3 | >    | 4 | 5 | 6 |
   *   +---+---+---+            +---+---+---+      +---+---+---+
   *   | 4 | 5 | 6 |
   *   +---+---+---+
   * The first position ('x') is reserved for other content and does not play a
   * role here.
   * The matrix answers the question: "If there are n selected content items,
   * which position(s) should hold the fallback content?".
   *
   * @param int $count
   *   The number of positioned content items available for the home page.
   *
   * @return int[]
   *   A list of the position numbers that should be filled with fallback items.
   */
  protected function _izi_apicontent_get_home_fallback_positions($count) {
    $fallback_position_matrix = [
      0 => [1, 2, 3, 4, 5, 6, 7, 8, 9],
      1 => [2, 3, 4, 5, 6, 7, 8, 9],
      2 => [2, 3, 5, 6, 7, 8, 9],
      3 => [2, 4, 6, 7, 8, 9],
      4 => [4, 6, 7, 8, 9],
      5 => [6, 7, 8, 9],
      6 => [7, 8, 9],
      7 => [8, 9],
      8 => [9],
    ];

    if (isset($fallback_position_matrix[$count])) {
      return $fallback_position_matrix[$count];
    }
    return [];
  }

  /**
   * @param \Triquanta\IziTravel\DataType\FeaturedMtgObjectInterface $featured_item
   *   The featured MTG object.
   * @param int $index
   *   The position of this item on its page.
   * @return mixed[]
   *   A Drupal render array.
   * @throws \Exception
   */
  protected function izi_apicontent_build_featured_mtg_object(FeaturedMtgObjectInterface $featured_item, $index = 0) {
    $build = [
      '#theme' => 'izi_featured_mtg_object',
    ];

    $build['#title'] = XSS::filter($featured_item->getName());
    $path = $this->iziObjectService->izi_apicontent_path($featured_item);

    $build['#path'] = $path;
    $build['#url'] = $path;

    $images = $featured_item->getImages();
    if (count($images)) {
      $image = reset($images);
      $build['#image_url'] = $this->iziObjectService->izi_apicontent_media_url($image);
    }
    else {
      $build['#image_url'] = '/' . \Drupal::service('extension.list.module')->getPath('izi_apicontent') . '/img/frontpage-placeholder.jpg';
    }

    $country_uuid = $featured_item->getCountryUuid();
    if (!empty($country_uuid)) {
      $build['#country_link'] = $this->iziObjectService->izi_apicontent_link(
        $this->iziObjectService->izi_apicontent_get_country_name($country_uuid),
        $country_uuid,
        IZI_APICONTENT_TYPE_COUNTRY,
        ['#attributes' => ['class' => 'featured-main-item-country']]
      );
    }
    $city_uuid = $featured_item->getCityUuid();
    if (!empty($city_uuid)) {
      $build['#city_link'] = izi_apicontent_link(izi_apicontent_get_city_name($city_uuid), $city_uuid, IZI_APICONTENT_TYPE_CITY, ['attributes' => ['class' => 'featured-main-item-city']]);
    }
    else {
      $build['#city_link'] = '';
    }

    $build['#object_type'] = izi_apicontent_get_sub_type($featured_item);

    $mtgcontent_compact = izi_apicontent_object_load($featured_item->getUuid(), IZI_APICONTENT_TYPE_MTG_OBJECT, MultipleFormInterface::FORM_COMPACT);

    $publisher = $mtgcontent_compact->getPublisher();
    if (!empty($publisher)) {
      $publisher_uuid = $publisher->getUuid();

      if (!empty($publisher_uuid)) {
        $cp_url = izi_apicontent_path($publisher->getUuid(), IZI_APICONTENT_TYPE_PUBLISHER);
        $content_provider = t('by') . ' ' . l($publisher->getTitle(), $cp_url);

        $build['#content_provider'] = $content_provider;
      }
    }

    if (empty($build['#content_provider'])) {
      $build['#content_provider'] = '';
    }

    return $build;
  }

}
