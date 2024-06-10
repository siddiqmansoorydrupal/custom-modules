<?php

namespace Drupal\izi_search;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Triquanta\IziTravel\DataType\CityInterface;
use Triquanta\IziTravel\DataType\CompactMtgObjectInterface;
use Triquanta\IziTravel\DataType\CountryInterface;
use Triquanta\IziTravel\DataType\MultipleFormInterface;
use Triquanta\IziTravel\DataType\PlaybackInterface;

/**
 * Service description.
 */
class IziSearchService {


  /**
   * The \Drupal\izi_apicontent\LanguageService service.
   *
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected IziObjectService $object_service;

  /**
   * The \Drupal\izi_apicontent\LanguageService service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected LanguageService $language_service;

  /**
   * IziSearchController constructor.
   *
   * @param \Drupal\izi_libizi\Libizi $libizi
   * @param \Drupal\izi_apicontent\LanguageService $language_service
   */
  public function __construct(
    IziObjectService $object_service,
    LanguageService $language_service,
  ) {
    $this->object_service = $object_service;
    $this->language_service = $language_service;
  }

  /**
   * @param \Triquanta\IziTravel\DataType\CompactMtgObjectInterface $object
   *   A MtgObject.
   *
   * @return mixed[]
   *   A Drupal render array.
   */
  public function izi_search_build_object_teaser(CompactMtgObjectInterface $object) {
    $build = [];

    if (!empty($object)) {
      $images = $object->getImages();
      $city = $object->getCity();
      $country = $object->getCountry();
      $title = Xss::filter($object->getTitle());

      $build = [
        '#theme' => 'izi_object_teaser',
        '#title' => $title,
        '#type' => $this->getContentType($object),
        '#uuid' => $object->getUuid(),
        '#url' => $this->object_service->izi_apicontent_url($object, IZI_APICONTENT_TYPE_MTG_OBJECT),
        '#languages' => $this->izi_search_build_object_teaser_languages($object),
        '#city' => $city
          ? $this->object_service->izi_apicontent_link($this->izi_search_country_city_translated_title($city), $city->getUuid(), IZI_APICONTENT_TYPE_CITY)
          : NULL,
        '#country' => $country
          ? $this->object_service->izi_apicontent_link($this->izi_search_country_city_translated_title($country), $country->getUuid(), IZI_APICONTENT_TYPE_COUNTRY)
          : NULL,
      ];
      if ($images) {
        $build['#image'] = [
          'image_small' => $this->object_service->izi_apicontent_media_url($images[0], $object, ['size' => '480x360']),
          'uuid' => $images[0]->getUuid(),
        ];
      }
    }

    return $build;
  }

  /**
   * Get content type.
   */
  private function getContentType($object) {
    if ($object->getType() == 'tour') {
      $uuid = $object->getUuid();
      $obj = $this->object_service->loadObject($uuid, IZI_APICONTENT_TYPE_MTG_OBJECT, MultipleFormInterface::FORM_FULL);
      if (!empty($obj)) {
        $content = $this->object_service->get_object_language_content($obj->getContent());
        if (!empty($content)) {
          $playback = $content->getPlayback();
          if ($playback instanceof PlaybackInterface && $playback->getType() === 'quest') {
            return "<div class='s-output-item-info-tour'>quest</div>";
          }
        }
      }
      return $this->object_service->izi_apicontent_get_sub_type($object);
    }
    return $this->object_service->izi_apicontent_get_sub_type($object);
  }

  /**
   * Create the language links on teaser displays.
   *
   * @param \Triquanta\IziTravel\DataType\CompactMtgObjectInterface $object
   *
   * @return mixed[]
   *   A Drupal render array.
   */
  private function izi_search_build_object_teaser_languages($object) {
    $output['languages'] = [
      '#items' => [],
      '#theme' => 'item_list',
      '#attributes' => [
        'class' => ['content-languages'],
      ],
    ];

    // Get all available languages of this object.
    $languages = $object->getAvailableLanguageCodes();

    // Sort language codes alphabetically.
    sort($languages);

    $count = count($languages);

    // When there are more than four languages we need to only show the first three, the others are in a dropdown.
    if ($count > 4) {
      $languages_visible = array_slice($languages, 0, 3);
      $languages_hidden = array_slice($languages, 3);
    }
    else {
      $languages_visible = $languages;
    }

    // Create links for the visible languages.
    foreach ($languages_visible as $language) {
      $output['languages']['#items'][] = $this->object_service->izi_apicontent_link($language, $object->getUuid(), IZI_APICONTENT_TYPE_MTG_OBJECT, [], $language);
    }
    // Create links for the hidden languages (in dropdown)
    if (!empty($languages_hidden)) {

      $more_languages = [
        '#theme' => 'item_list',
        '#items' => [],
      ];
      foreach ($languages_hidden as $language) {
        $more_languages['#items'][] = $this->object_service->izi_apicontent_link($language, $object->getUuid(), IZI_APICONTENT_TYPE_MTG_OBJECT, [], $language);
      }

      $url = Url::fromRoute('<current>', [
        'fragment' => 'search',
        'attributes' => ['class' => ['content-languages-more']],
      ]);
      $link = Link::fromTextAndUrl('+' . (string) ($count - 3), $url);

      $output['languages']['#items'][3] = [
        '#wrapper_attributes' => ['class' => ['content-languages-more-wrapper']],
        ['#markup' => $link->toString()],
        $more_languages,
      ];
    }
    return $output;
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
  public function izi_search_country_city_translated_title($object, $language = '') {
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
      $languages = $this->_izi_search_fallback_languages($language);
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
   * Helper to get languages in a preferred fallback order.
   *
   * @param string $language
   *   (optional) Preferred fallback language. Defaults to the current interface language.
   *
   * @return array
   *   Array of fallback languages in the order of preference.
   */
  public function _izi_search_fallback_languages($language = '') {
    $languages = &drupal_static(__FUNCTION__ . ':' . $language);

    if (!isset($languages)) {
      $preferred_language = $language ? $language : $this->language_service->izi_apicontent_get_interface_language();
      $languages = explode(' ', IZI_APICONTENT_LANGUAGE_FALLBACK);
      $languages = array_unique(array_merge([$preferred_language], $languages));
    }

    return $languages;
  }

}
