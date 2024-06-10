<?php

namespace Drupal\izi_search\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Drupal\izi_libizi\Libizi;
use Drupal\izi_search\IziSearchService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Triquanta\IziTravel\DataType\CityInterface;
use Triquanta\IziTravel\DataType\CompactMtgObjectInterface;
use Triquanta\IziTravel\DataType\CountryInterface;
use Triquanta\IziTravel\DataType\FeaturedMtgObjectInterface;
use Triquanta\IziTravel\DataType\MtgObjectInterface;
use Triquanta\IziTravel\DataType\MultipleFormInterface;

/**
 * Returns responses for izi_search routes.
 */
class BaseController extends ControllerBase {

  /**
   * Search service.
   *
   * @var \Drupal\izi_search\IziSearchService
   */
  protected IziSearchService $search_service;

  /**
   * The izi_libizi.libizi service.
   *
   * @var \Drupal\izi_libizi\Libizi
   */
  protected Libizi $libizi;

  /**
   * The \Drupal\izi_apicontent\LanguageService service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected LanguageService $language_service;

  /**
   * The \Drupal\izi_apicontent\LanguageService service.
   *
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected IziObjectService $object_service;

  /**
   * IziSearchController constructor.
   *
   * @param \Drupal\izi_libizi\Libizi $libizi
   * @param \Drupal\izi_apicontent\LanguageService $language_service
   */
  public function __construct(
    IziSearchService $search_service,
    Libizi $libizi,
    IziObjectService $object_service,
    LanguageService $language_service,
  ) {
    $this->search_service = $search_service;
    $this->libizi = $libizi;
    $this->object_service = $object_service;
    $this->language_service = $language_service;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('izi_search.izi_search_service'),
      $container->get('izi_libizi.libizi'),
      $container->get('izi_apicontent.izi_object_service'),
      $container->get('izi_apicontent.language_service'),
    );
  }

  /**
   * Page callback for AJAX autocomplete results.
   *
   * Use search string to find corresponding MTG objects which form search suggestion.
   *
   * @param string $search_string
   *   (part of) search request entered by user.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Render array with node titles as links to their node pages.
   */
  public function izi_search_ajax_get_suggestions($search_string = '') {
    global $language;
    $langcode = $language->language;

    // Sanitize search input.
    $search_string = Xss::filter($search_string);
    // Remove whitespace from start and end.
    $search_string = trim($search_string);

    // Can I get the data from the cache?
    // Create the cid.
    $cid = "izi_search_suggestions:$langcode:$search_string";

    // Do we have a cache hit?
    if ($cache = \Drupal::cache()->get($cid)) {
      // If ($cache = cache_get($cid, 'cache_page')) {
      // Use the cached data.
      $suggestion = $cache->data;
    }
    else {
      // Types.
      $types = [
        IZI_APICONTENT_TYPE_CITY,
        IZI_APICONTENT_TYPE_COUNTRY,
        MtgObjectInterface::TYPE_TOUR,
        MtgObjectInterface::TYPE_MUSEUM,
      ];

      // Do a search, limit to 6 most popular results, including cities and countries.
      $objects = $this->izi_search_get_compact_objects('search', $search_string, 12, 0, 'all', ['country', 'translations'], 'popularity:desc', $types);

      $results = [];
      // If we have results from our query, collect them to an array als links.
      if (count($objects)) {
        foreach ($objects as $object) {
          /** @var \Triquanta\IziTravel\DataType\CompactMtgObjectInterface $object */
          $object_type = $this->object_service->izi_apicontent_get_object_type($object);
          $type = $this->object_service->izi_apicontent_get_sub_type($object);

          // If the object is a city or country get the translated name.
          if ($object_type == IZI_APICONTENT_TYPE_CITY || $object_type == IZI_APICONTENT_TYPE_COUNTRY) {
            $link_title = $this->izi_search_country_city_translated_title($object);
          }
          else {
            $link_title = $object->getTitle();
          }

          // If the object is a city we need to add the translated country name to the string.
          if ($object_type == IZI_APICONTENT_TYPE_CITY) {
            $country_uuid = $object->getLocation()->getCountryUuid();

            // Cache translated country names permanently to prevent unnecessary api calls
            // Create the title cache id per interface language.
            $country_title_cid = 'izi_search_suggestions:title:' . $this->language_service->izi_apicontent_get_interface_language() . ':' . $country_uuid;

            // Do we have a cache hit?
            // @todo (legacy) create custom cache bin
            if ($cache = \Drupal::cache()->get($country_title_cid)) {
              // If ($cache = cache_get($country_title_cid, 'cache_page')) {
              // Use the cached data.
              $country_title = $cache->data;
            }
            else {
              // Load country object to be able to get title
              // Get an API client.
              $country = $this->libizi->getLibiziClient()->getCountryByUuid($this->language_service->get_preferred_content_languages(), $country_uuid)->setIncludes(['translations'])->execute();

              // Create translated country name.
              $country_title = $this->izi_search_country_city_translated_title($country);

              // Store translated title in cache.
              \Drupal::cache()->set($country_title_cid, $country_title);
            }

            $link_title .= ', ' . $country_title;
          }

          $link_title = mb_strimwidth(Xss::filter($link_title), 0, 60, "...");

          $results[] = $this->object_service
            ->izi_apicontent_link(
              $link_title,
              $object->getUuid(),
              $object_type,
              [
                'attributes' => [
                  'class' => [$type],
                ],
                'html' => TRUE,
              ]
            );

        }
        $results[] = [
          '#type'    => 'link',
          '#title'   => $this->t('See all search results'),
          '#href'    => '/search/' . $search_string,
        ];
      }
      // Create unordered list from result set as render array.
      $suggestion = [
        'suggestions' => [
          '#items' => $results,
          '#theme' => 'item_list',
          '#attributes' => [
            'class' => ['search-suggestions'],
          ],
        ],
      ];

      // Store output in the cache
      //      cache_set($cid, $suggestion, 'cache_page', CACHE_TEMPORARY);.
      \Drupal::cache()->set($cid, $suggestion, time() + 86400);
    }
    $output = \Drupal::service('renderer')->renderRoot($suggestion);
    $response = new Response();
    $response->setContent($output);
    return $response;
  }

  /**
   * Search results AJAX load more page callback
   * Delivers JSON with info whether to show a load more, the next search offset and the rendered results
   *
   * @param string $search_type
   *   The type of search/browse action, either 'search', 'city' or 'country'.
   * @param string $search_string
   *   The string or uuid to search or browse.
   * @param int $offset
   *   The number of items to skip when performing the search query.
   * @param string $filter_type
   * @param string $filter_lang
   */
  public function izi_search_ajax_load_more($search_type, $search_string, $offset, $filter_type = 'all', $filter_lang = 'all') {
    $return = [];
    $includes = ['country', 'city', 'translations'];

    // Get objects.
    $objects = $this->izi_search_get_compact_objects($search_type, $search_string, IZI_SEARCH_RESULTS_AMOUNT + 1, $offset, $filter_lang, $includes, 'popularity:desc', $filter_type);

    // Check if we need a load more link.
    $load_more_type = FALSE;
    if (count($objects) > IZI_SEARCH_RESULTS_AMOUNT) {
      $load_more_type = $search_type;
      $return['load_more'] = TRUE;
    }
    else {
      $return['load_more'] = FALSE;
    }

    // Set the new search offset for the load more link.
    $return['offset'] = $offset + IZI_SEARCH_RESULTS_AMOUNT;
    // Create the loaded search results HTML.
    $return['results'] = \Drupal::service('renderer')->renderRoot($this->izi_search_build_results($objects));
    // Deliver JSON.
    return new JsonResponse($return);
  }

  /**
   *
   */
  protected function izi_search_block_view($delta, $search_string = '', $filters = []) {
    $block = [];

    switch ($delta) {
      case 'izi_search_browse':
        // $search_form = drupal_get_form('izi_search_search_form');
        $parameter = 'new';
        $search_form = \Drupal::formBuilder()
          ->getForm('Drupal\izi_search\Form\SearchForm', $parameter);

        $browse_list = $this->izi_search_browse_list();
        $block['subject'] = $this->t('Search Browse');
        $block['content'] = [
          '#theme' => 'izi_search_block',
          '#search_form' => $search_form,
          '#slides' => $browse_list['slides'],
          '#countries_with_cities' => $browse_list['countries_with_cities'],
          '#search_string' => $search_string,
          '#filters' => $filters,
          '#cache' => [
            'contexts' => [
              'url',
              'languages:language_content',
              'languages:language_interface',
            ],
          ],
        ];
        $block['#attached']['library'][] = 'izi_search/izitravel.fotorama';
        break;

      case 'featured_content_search_page':
        $featured_content = $this->object_service->izi_apicontent_home_content_load();
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
        $fallback_positions = $this->object_service->_izi_apicontent_get_home_fallback_positions(count($positioned_content));
        $position = 1;
        $items = [];
        while ($position <= 9) {
          // Check if this position should be filled with fallback content.
          if (in_array($position, $fallback_positions)) {
            // Find the best available fallback content.
            if (count($fallback_content)) {
              // Use an MTG object as fallback content.
              $items[$position] = $this->object_service->izi_apicontent_build_featured_mtg_object(array_shift($fallback_content), $position);
            }
          }
          else {
            $items[$position] = $this->object_service->izi_apicontent_build_featured_mtg_object(array_shift($positioned_content), $position);
          }
          $position++;
        }
        $build = [
          '#theme' => 'izi_featured_content',
          '#title' => t('Featured audio guides'),
          '#featured_items' => $items,
        ];

        $block['content'] = $build;
        break;
    }

    return $block;
  }

  /**
   * Create the countries / cities browse list content.
   */
  private function izi_search_browse_list() {
    $interface_lang = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $cid = 'izi_search_browse:' . $interface_lang;
    if ($cache = \Drupal::cache()
      ->get($cid)) {
      return $cache->data;
    }

    $browse_list = [
      'slides' => [
        'menu-slider-one' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['menu-slider-one'],
          ],
        ],
        'menu-slider-two' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['menu-slider-two'],
          ],
          ['#markup' => '<a class="all_countries" href="#">' . $this->t('all countries') . '</a><div class="fotorama-cities-container"></div>'],
        ],
      ],
      'countries_with_cities' => [],
    ];

    // Get all languages.
    $languages = $this->language_service->get_preferred_content_languages();

    // Get all countries. We load in chunks as the maximum per request is 300.
    $countries = [];
    $offset = 0;
    $chunk_size = 250;
    $request = $this->libizi->getLibiziClient()
      ->getCountries($languages)
      ->setIncludes(['translations'])
      ->setLimit($chunk_size);
    while ($result = $request->execute()) {
      $countries = array_merge($countries, $result);
      $offset += $chunk_size;
      $request->setOffset($offset);
      if (count($result) < $chunk_size) {
        break;
      }
    }

    // Create array of country links.
    $country_links_titles = [];
    // Create array of countries with as key the link and as value the translated name.
    $country_links = [];
    foreach ($countries as $country) {
      // Link title.
      $title = $this->izi_search_country_city_translated_title($country);

      // Link options, set data attribute with country code, used by JS.
      $options = [
        'attributes' => [
          'data-country' => [$country->getCountryCode()],
        ],
      ];
      $link = $this->object_service->izi_apicontent_link($title, $country->getUuid(), IZI_APICONTENT_TYPE_COUNTRY, $options);
      $country_links_titles[$country->getCountryCode()] = $title;

      // Use link as key and title as value to be able to do natsort.
      $country_links[(string) $link] = $title;
    }

    // Order the countries list naturally by name.
    natsort($country_links);
    $country_links = array_flip($country_links);
    // Create the subslides for countries.
    $browse_list['slides']['menu-slider-one'] += $this->_izi_search_create_slides_with_link_lists($country_links, 36);

    // Get all cities. We load cities in chunks as the maximum per request is 300.
    $cities = [];
    $offset = 0;
    $chunk_size = 250;
    $request = $this->libizi->getLibiziClient()
      ->getCities($languages)
      ->setIncludes(['translations'])
      ->setLimit($chunk_size);

    while ($result = $request->execute()) {
      $cities = array_merge($cities, $result);
      if (count($result) < $chunk_size) {
        break;
      }
      $offset += $chunk_size;
      $request->setOffset($offset);
    }

    // Create array of countries with sub arrays of cities with as key the uuid and as value the translated name.
    $cities_basic = [];
    $create_all_link = [];

    foreach ($cities as $city) {
      $country_code = $city->getLocation()->getCountryCode();

      // Link title.
      $title = $this->izi_search_country_city_translated_title($city);

      // Use link as key and title as value to be able to do natsort.
      $cities_basic[$country_code][(string) $this->object_service->izi_apicontent_link($title, $city->getUuid(), IZI_APICONTENT_TYPE_CITY)] = $title;

      // Only create 'all guides' link per country once.
      if (!isset($create_all_link[$country_code])) {
        $title = $country_links_titles[$country_code];
        $cities_basic[$country_code][(string) $this->object_service->izi_apicontent_link($title . ': ' . t('All audio guides'), $city->getLocation()->getCountryUuid(), IZI_APICONTENT_TYPE_COUNTRY)] = 'all_link';
        $create_all_link[$country_code] = TRUE;
      }
    }

    foreach ($cities_basic as $code => $country) {
      // Get the all link.
      $all_link = array_search('all_link', $country);
      // Remove the all link from the list of links.
      unset($country[$all_link]);

      // Order the city lists naturally by name.
      natsort($country);

      // Add the all link back to the top of the list.
      $country = [$all_link] + array_flip($country);

      // Set data attribute with country code, used by JS.
      $browse_list['countries_with_cities'][$code] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['browse-cities-by-country'],
          'data-country' => [$code],
        ],
      ];

      // Create the hidden sub-slides for cities per counrty.
      $browse_list['countries_with_cities'][$code] += $this->_izi_search_create_slides_with_link_lists($country, 27);
    }

    // Cache the browse list for
    //    - a day:(1 * 24 * 60 * 60) = 86400
    //    - an hour: (60 * 60)) = 3600.
    \Drupal::cache()->set($cid, $browse_list, time() + (60 * 60));

    return $browse_list;
  }

  /**
   * Gets the translated title of a city or country.
   *
   * @param \Triquanta\IziTravel\DataType\CompactCityInterface|\Triquanta\IziTravel\DataType\CompactCountryInterface $object
   *   City or country object for which to get the title.
   * @param string $language
   *   Preferred fallback language. Defaults to the current interface language.
   *
   * @return string
   *   Translated title
   *
   * @throws \InvalidArgumentException
   *   When $object is of the wrong type.
   */
  private function izi_search_country_city_translated_title($object, $language = '') {
    if (!$object instanceof CountryInterface && !$object instanceof CityInterface) {
      throw new \InvalidArgumentException('$object must implement \Triquanta\IziTravel\DataType\CompactCityInterface or \Triquanta\IziTravel\DataType\CompactCountryInterface.');
    }

    $static_id = __FUNCTION__ . ':' . $object->getUuid() . ':' . $language;
    $title = &drupal_static($static_id);

    if (!isset($title)) {
      $titles = [];

      // Determine the order of languages in which we will get the country or city
      // names. Translation of country or city name may not be available in every
      // language.
      $languages = $this->search_service->_izi_search_fallback_languages($language);
      $languages = array_fill_keys($languages, NULL);

      // Get the translated titles from the country/city object.
      foreach ($object->getTranslations() as $translation) {
        $titles[$translation->getLanguageCode()] = $translation->getName();
      }
      $titles_sorted = array_filter(array_replace($languages, $titles));
      $title = reset($titles_sorted);
    }
    return $title;
  }

  /**
   * Helper function to create fotorama slides which consist of columns with links.
   *
   * @param $slides
   * @param int $columns
   *   amount of columns per slide.
   * @param $type
   *   api content type
   *
   * @return mixed
   *   Render array
   */
  private function _izi_search_create_slides_with_link_lists($items, $per_slide) {
    $slides = [];

    // Split the array in sub arrays of an amount of items per slide.
    $slides_full = array_chunk($items, $per_slide);

    foreach ($slides_full as $i => $slide) {
      // Split each slide in (items per slide / 9) columns.
      $column = $this->_izi_search_fill_chunck($slide, $per_slide / 9);
      $slides[$i] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['slide'],
        ],
      ];

      foreach ($column as $j => $part) {
        $slides[$i][$j] = [
          '#theme' => 'item_list',
          '#attributes' => [
            'class' => ['browse-column'],
          ],
        ];
        foreach ($part as $item) {
          $slides[$i][$j]['#items'][] = ["#markup" => $item];
        }
      }
    }

    return $slides;
  }

  /**
   * Helper function to split array into multiple parts.
   *
   * It is used for example to create countries and cities listings split in columns underneath the search.
   */
  private function _izi_search_fill_chunck($array, $parts) {
    $t = 0;
    $result = array_fill(0, $parts - 1, []);
    $max = ceil(count($array) / $parts);
    foreach ($array as $v) {
      count($result[$t]) >= $max and $t++;
      $result[$t][] = $v;
    }
    return $result;
  }

  /**
   * Page callback for the search page and similar pages like: country and city.
   *
   * @param string $search_type
   *   The type of search/browse action, either 'search', 'city' or 'country'.
   * @param string $search_string
   *   The string to search for or the uuid of the object being browsed.
   * @param string $filter_type
   * @param string $filter_lang
   *
   * @return mixed[]|RedirectResponse
   *   A Drupal render array.
   *
   * @throws \Exception
   */
  protected function izi_search_page_results_view($search_type, $search_string, $filter_type, $filter_lang) {

    $includes = ['country', 'city', 'translations'];

    // Get optional data from URL.
    $filter_type = izi_metatag_validate_filter_type($filter_type, 'all');
    $filter_lang = $this->language_service->izi_apicontent_get_content_language_from_url('all');
    $search_block = $this->izi_search_block_view(
      'izi_search_browse',
      $search_string,
      $this->_izi_search_filters_options($search_type, $search_string, $filter_type, $filter_lang),
    );

    $output = [
      '#theme' => 'izi_search_results',
      '#search_block' => $search_block['content'],
    ];

    // Set the title country 'search'.
    if ($search_type == 'city' || $search_type == 'country') {
      $title = \Drupal::service('title_resolver')->getTitle(\Drupal::request(), \Drupal::routeMatch()->getRouteObject());
      $title = Html::decodeEntities($title);
      $output['#title'] = $this->t('@title', ['@title' => $title]);
    }

    // Do a search, limit to the 12 + 1 most popular results
    // We only show 12, but get 13 to see if there are more items.
    $objects = $this->izi_search_get_compact_objects($search_type, $search_string, IZI_SEARCH_RESULTS_AMOUNT + 1, 0, $filter_lang, $includes, 'popularity', $filter_type);

    $count = count($objects);

    // Redirect to page when only one result is found and we are on a search page.
    if ($count == 1 && $search_type == 'search') {
      if ($objects[0] instanceof CompactMtgObjectInterface) {
        $path = $this->object_service->izi_apicontent_path($objects[0]->getUuid(), IZI_APICONTENT_TYPE_MTG_OBJECT, $objects[0]->getLanguageCode());
        return new RedirectResponse(Url::fromUri("internal:/{$path}")->toString());
      }
      else {
        throw new \Exception('Couldn\'t redirect to search result.');
      }
    }
    // If we have multiple results from our query,.
    elseif ($count > 0) {
      // Check if we need a load more link.
      $load_more_type = FALSE;
      if ($count > IZI_SEARCH_RESULTS_AMOUNT) {
        $load_more_type = $search_type;
        $output['#load_more'] = [
          'search-offset' => IZI_SEARCH_RESULTS_AMOUNT,
          'search-type' => $search_type,
          'search-string' => $search_string,
          'filter-type' => $filter_type,
          'filter-lang' => $filter_lang,
        ];
      }
      $output['#results'] = $this->izi_search_build_results($objects);
      $output['#attached']['library'][] = 'izi_search/izi_search.results';
    }
    return $output;
  }

  /**
   * Get objects for all languages by a search string.
   *
   * Allow string input for $languages and $types in case we need to get the options from the url.
   *
   * @param $search_type
   *   The type of search/browse action, either 'search', 'city' or 'country'.
   * @param string $search_string
   *   The user search input string.
   * @param int $limit
   *   Amount of results to get.
   * @param int $offset
   *   Search results offset.
   * @param string|array $languages
   *   Array of languages to include in the search, or 'all' to include all.
   * @param string[] $includes
   * @param string $sort
   *   Sort by field:direction.
   * @param array|string $types
   *   Object sub types to get.
   *
   * @return \Triquanta\IziTravel\DataType\CompactMtgObjectInterface[]
   *
   * @throws \Exception
   */
  private function izi_search_get_compact_objects($search_type, $search_string, $limit, $offset = 0, $languages = 'all', $includes = [], $sort = 'popularity:desc', $types = 'all') {
    // If languages is all get the languages in preferred order.
    if ($languages == 'all') {
      $languages = $this->language_service->get_preferred_content_languages();
    }

    // If we are filtering one a particular language and the input is a string add it to the language array.
    elseif (is_string($languages)) {
      $languages = [$languages];
    }

    if (is_string($types)) {
      // If there is no special filter for MTG object type search all.
      if ($types == 'all') {
        $types = [MtgObjectInterface::TYPE_TOUR, MtgObjectInterface::TYPE_MUSEUM];
      }
      elseif ($types == 'tour') {
        $types = [
          MtgObjectInterface::TYPE_TOUR . '_random',
          MtgObjectInterface::TYPE_TOUR . '_sequential',
        ];
      }
      elseif ($types == 'museum') {
        $types = [
          MtgObjectInterface::TYPE_MUSEUM,
        ];
      }
      elseif ($types == 'quest') {
        $types = [
          MtgObjectInterface::TYPE_TOUR . '_quest',
        ];
      }
      // If not searching for all, tour or museum throw exeption.
      else {
        throw new \Exception('Unknown object type: ' . $types);
      }
    }

    if ($search_type == 'city' || $search_type == 'country') {
      // To be able to sort by popularity, we need to do a search instead
      // of a children request. The amount of results is the same.
      /** @var \Triquanta\IziTravel\Request\Search $request */
      $request = $this->libizi->getLibiziClient()
        ->search($languages, '');
      $request->setRegion($search_string);
    }
    else {
      // @todo (legacy) sanitizing may prevent some functionality
      // Sanitize search input.
      $search_string = Xss::filter($search_string);
      /** @var \Triquanta\IziTravel\Request\Search $request */
      $request = $this->libizi->getLibiziClient()
        ->search($languages, $search_string)
        ->setSort($sort, 'asc');
    }

    return $request
      ->setTypes($types)
      ->setIncludes($includes)
      ->setForm(MultipleFormInterface::FORM_COMPACT)
      ->setLimit($limit)
      ->setOffset($offset)
      ->execute();
  }

  /**
   * Build a render array with results (and load more link when needed)
   *
   * @param \Triquanta\IziTravel\DataType\CompactMtgObjectInterface[] $object
   *
   * @return array
   *   Rendered results
   */
  private function izi_search_build_results($objects) {
    $search_results = [];
    // Unset the last object, we don't want to show it.
    unset($objects[IZI_SEARCH_RESULTS_AMOUNT]);
    // Results are only tours and museums!
    foreach ($objects as $object) {
      if ($object instanceof CompactMtgObjectInterface) {
        $result = $this->search_service->izi_search_build_object_teaser($object);
        $search_results[] = $result;
      }
    }
    return $search_results;
  }

  /**
   * @param string $search_type
   *   The type of search/browse action, either 'search', 'city' or 'country'.
   * @param string $search_string
   * @param string $filter_type
   * @param string $filter_lang
   * @return array
   * @throws \Exception
   */
  private function _izi_search_filters_options($search_type, $search_string, $filter_type = 'all', $filter_lang = 'all') {
    // TO DO - Change 'quest' for MtgObjectInterface::TYPE_QUEST
    // To do this, we need to tag a new version for libizi library from Triquanta.
    $types = [
      MtgObjectInterface::TYPE_TOUR => t('Tours'),
      MtgObjectInterface::TYPE_MUSEUM => t('Museums'),
      'quest' => t('Quests'),
    ];
    $languages = [];

    $cid = 'izi_search_filter_lang:' . $search_string;

    // Do we have a cache hit?
    if ($cache = \Drupal::cache()
      ->get($cid)) {
      // Use the cached data.
      $languages = $cache->data;
    }
    else {
      // Get all language known.
      $all_languages = $this->language_service->get_language_names();

      // Get all results and find all unique content languages
      // This is a workaround the API limitations, there is currently no way of getting all available languages for a search (this is possible for publisher children)
      $all_results = $this->izi_search_get_compact_objects($search_type, $search_string, 250);
      foreach ($all_results as $result) {
        $languages = array_merge($languages, $result->getAvailableLanguageCodes());
      }

      // Add the human readable names.
      $languages = array_intersect_key($all_languages, array_flip($languages));

      // Cache the available languages list for a day.
      \Drupal::cache()->set($cid, $languages, time() + 86400);

    }

    // Get current search string.
    $current_search_string = $search_string;

    // Link option with 'selected' class.
    $current_class = ['attributes' => ['class' => ['selected']]];

    // Get current filter type, url part (code) and human-readable name (hrn)
    if ($filter_type !== 'all') {
      $current_type = [
        'code' => $filter_type,
        'hrn' => $types[$filter_type] ?? $filter_type,
      ];
    }
    else {
      $current_type = [
        'code' => 'all',
        'hrn' => t('All types'),
      ];
    }

    // Get current search language, url part (code) and human readable name (hrn)
    if ($filter_lang !== 'all') {
      $current_lang = [
        'code' => $filter_lang,
        'hrn' => $languages[$filter_lang],
      ];
    }
    else {
      $current_lang = [
        'code' => '',
        'hrn' => t('Any language'),
      ];
    }

    $uri = "internal:/{$search_type}/{$current_search_string}/{$current_lang['code']}";
    $url = Url::fromUri($uri);

    $link = Link::fromTextAndUrl(t('All types'), $url);
    $filters = [
      'types' => [
        'current' => $current_type['code'],
        'current_hrn' => $current_type['hrn'],
        'list' => [$link],
      ],
    ];

    foreach ($types as $type => $type_label) {
      // Set 'selected' class if this filter is active.
      if ($current_type['code'] === $type) {
        $options = $current_class;
      }
      else {
        $options = [];
      }

      $url = Url::fromUri("internal:/{$search_type}/{$current_search_string}/{$type}/{$current_lang['code']}", $options);
      $link = Link::fromTextAndUrl($type_label, $url);
      $filters['types']['list'][] = $link;
    }

    if ($search_type !== 'search') {
      $url = Url::fromUri("internal:/{$search_type}/{$current_search_string}");
      $link = Link::fromTextAndUrl(t('Any language'), $url);
      $filters = $filters + [
        'lang' => [
          'current' => $current_lang['code'],
          'current_hrn' => $current_lang['hrn'],
          'list' => [$link],
        ],
      ];

      foreach ($languages as $code => $language) {
        // Set 'selected' class if this filter is active.
        if ($current_lang['code'] === $code) {
          $options = $current_class;
        }
        else {
          $options = [];
        }

        $url = Url::fromRoute('izi_search.city', [
          'city' => $current_search_string,
          'filter_type' => $current_type['code'],
          'filter_lang' => $code,
        ]);
        $filters['lang']['list'][] = Link::fromTextAndUrl($language, $url);
      }
    }

    return $filters;
  }

}
