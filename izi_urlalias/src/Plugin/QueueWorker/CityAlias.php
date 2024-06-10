<?php

namespace Drupal\izi_urlalias\Plugin\QueueWorker;

use Drupal\Core\Language\Language;
use Triquanta\IziTravel\DataType\CompactCity;
use Triquanta\IziTravel\DataType\CountryCityTranslation;
use Triquanta\IziTravel\DataType\MultipleFormInterface;

/**
 * Defines 'izi_urlalias_city_alias' queue worker.
 *
 * @QueueWorker(
 *   id = "izi_urlalias_city_alias",
 *   title = @Translation("City Alias Worker"),
 *   cron = {"time" = 60}
 * )
 */
class CityAlias extends IziQueueWorkerBase {

  public const QUEUE_NAME = 'izi_urlalias_city_alias';

  /**
   *
   */
  public function processItem($data) {
    $this->izi_urlalias_update_city_url_alias($data);
  }

  /**
   *
   */
  public function izi_urlalias_update_city_url_alias(CompactCity $object) {
    $uuid = $object->getUuid();

    $this->logger->info($this->t('IZI UrlAlias - Processing city @uuid', ['@uuid' => $uuid]));

    // If the object is new or updated, create a new alias.
    $status = izi_urlalias_get_status($uuid, $object->getLanguageCode());
    $revision_hash = $object->getRevisionHash();
    $has_alias = $this->izi_urlalias_uuid_has_alias('city', $uuid, $object->getLanguageCode());

    if (!$status || $status->hash != $revision_hash || !$has_alias) {
      $country_uuid = $object->getLocation()->getCountryUuid();
      $translations = _izi_urlalias_keyed_translations($object->getTranslations());
      $languages = $this->iziLanguages->izi_apicontent_get_active_interface_languages();

      foreach ($languages as $langcode) {

        // Not for all languages a translated object may be available. But still
        // we want to create URLs for these languages. When not available we use
        // the English translation of the object.
        $translation = array_key_exists($langcode, $translations) ? $translations[$langcode] : $this->_izi_urlalias_build_country_city_translation($translations['en']->getName(), $langcode);
        $interface_langcode = izi_urlalias_izi_to_drupal_language($langcode);
        $country_name = $this->izi_apicontent_get_country_name($country_uuid, $langcode);

        foreach (['all', 'museum', 'tour', 'quest'] as $type) {
          $url = $this->izi_urlalias_city_clean_url($translation, $type, $country_name);
          $source = $this->izi_urlalias_country_city_system_url('city', $uuid, $type);
          izi_urlalias_update_alias($uuid, 'city', $source, $url, $interface_langcode, TRUE, Language::LANGCODE_NOT_SPECIFIED, 'City');
        }
      }
    }
  }

  /**
   * Returns the localized country name.
   *
   * Will return the country name in the preferred language or fall back to the
   * first available one.
   *
   * @param $uuid
   *   UUID of the country.
   * @param string|null $selected_language
   *   (option) Language code for the localization. Will override the preferred
   *   language selection.
   *
   * @return string|array
   *   Localized country name or array of available country names if the language
   *   fallback fails.
   *
   * @throws \Exception
   */
  protected function izi_apicontent_get_country_name($uuid, string $selected_language = NULL): array|string {

    // Cache opportunity. Previously cached 24 hours.
    $country_name_cid = __FUNCTION__ . ':izi_apicontent_country_name:' . $uuid;
    $cache = &drupal_static($country_name_cid);
    if ($cache) {
      $country_names = unserialize($cache->data);
    }
    else {
      $country = $this->iziObjects->loadObject(
        $uuid,
        IZI_APICONTENT_TYPE_COUNTRY,
        MultipleFormInterface::FORM_COMPACT,
        ['translations']
      );
      $country_names = [];
      foreach ($country->getTranslations() as $translation) {
        $country_names[$translation->getLanguageCode()] = $translation->getName();
      }
    }

    if (isset($selected_language)) {
      return $country_names[$selected_language] ?? $country_names['en'];
    }

    $languages = izi_apicontent_get_preferred_content_languages();
    foreach ($languages as $language) {
      if (isset($country_names[$language])) {
        return $country_names[$language];
      }
    }
    return reset($country_names);
  }

  /**
   * Constructs a human-readable URL alias for City content.
   *
   * @param \Triquanta\IziTravel\DataType\CountryCityTranslation $object
   *   City object to create the URL for.
   * @param string $type
   *   Data type to be used as URL suffix.
   * @param string $country_name
   *   Localized country name.
   *
   * @return string
   *   Translated human-readable URL.
   */
  protected function izi_urlalias_city_clean_url(CountryCityTranslation $object, $type, $country_name): string {

    $name = _izi_urlalias_prepare_url_string($object->getName());
    $langcode = $object->getLanguageCode();
    $clean_url = '';

    switch ($type) {
      case 'all':
        $clean_url = $this->_izi_urlalias_translate_CountryCity_url($country_name, 'city-guides-in', $name, $langcode);
        break;

      case 'museum':
        $clean_url = $this->_izi_urlalias_translate_CountryCity_url($country_name, 'museum-tours-in', $name, $langcode);
        break;

      case 'tour':
        $clean_url = $this->_izi_urlalias_translate_CountryCity_url($country_name, 'walking-tours-in', $name, $langcode);
        break;

      case 'quest':
        $clean_url = $this->_izi_urlalias_translate_CountryCity_url($country_name, 'quests-in', $name, $langcode);
        break;
    }

    return $clean_url;

    // This code is unreachable on purpose. By placing translated strings here
    // they will be picked up by potx but will not be executed. These strings are
    // used as variable in a t() call.
    // @see _izi_urlalias_CountryCity_url_translations()
    t('city-guides-in');
    t('museum-tours-in');
    t('walking-tours-in');
    t('quests-in');
  }

  /**
   * Construct the Drupal (system) URL for country and city content.
   *
   * @param $leading
   *   First URL segment. Usually 'country' or 'city'.
   * @param $uuid
   *   Second URL segment. Content UUID.
   * @param $type
   *   Third URL segment. Usually 'all', 'museum' or 'tour'. 'all' will not be
   *   included in the URL.
   *
   * @return string
   *   Drupal URL.
   */
  protected function izi_urlalias_country_city_system_url($leading, $uuid, $type): string {

    $segments[] = $leading;
    $segments[] = $uuid;
    if ($type != 'all') {
      $segments[] = $type;
    }
    return implode('/', $segments);
  }

}
