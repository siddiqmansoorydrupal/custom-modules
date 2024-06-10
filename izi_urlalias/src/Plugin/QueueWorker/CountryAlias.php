<?php

namespace Drupal\izi_urlalias\Plugin\QueueWorker;

use Drupal\Core\Language\Language;
use Triquanta\IziTravel\DataType\CountryCityTranslation;

/**
 * Defines 'izi_urlalias_country_alias' queue worker.
 *
 * @QueueWorker(
 *   id = "izi_urlalias_country_alias",
 *   title = @Translation("Country Alias Worker"),
 *   cron = {"time" = 60}
 * )
 */
class CountryAlias extends IziQueueWorkerBase {

  public const QUEUE_NAME = 'izi_urlalias_country_alias';

  /**
   *
   */
  public function processItem($data) {

    $this->izi_urlalias_update_country_url_alias($data);
  }

  /**
   *
   */
  public function izi_urlalias_update_country_url_alias($object) {

    $uuid = $object->getUuid();

    $this->logger->info($this->t('IZI UrlAlias - Processing country @uuid', ['@uuid' => $uuid]));

    // If the object is new or updated, create a new alias.
    $status = izi_urlalias_get_status($uuid, $object->getLanguageCode());
    $revision_hash = $object->getRevisionHash();

    $has_alias = $this->izi_urlalias_uuid_has_alias('country', $uuid, $object->getLanguageCode());

    if (!$status || $status->hash != $revision_hash || !$has_alias) {

      $translations = _izi_urlalias_keyed_translations($object->getTranslations());

      $languages = $this->iziLanguages->izi_apicontent_get_active_interface_languages();

      foreach ($languages as $langcode) {

        // Not for all languages a translated object may be available. But still
        // we want to create URLs for these languages. When not available we use
        // the English translation of the object.
        $translation = array_key_exists($langcode, $translations)
          ? $translations[$langcode]
          : $this->_izi_urlalias_build_country_city_translation(
            $translations['en']->getName(),
            $langcode
          );

        $interface_langcode = izi_urlalias_izi_to_drupal_language($translation->getLanguageCode());

        foreach (['all', 'museum', 'tour', 'quest'] as $type) {
          $url = $this->izi_urlalias_country_clean_url($translation, $type);
          $source = $this->izi_urlalias_country_city_system_url('country', $uuid, $type);
          izi_urlalias_update_alias($uuid, 'country', $source, $url, $interface_langcode, TRUE, Language::LANGCODE_NOT_SPECIFIED, 'Country');
        }
      }
    }

    // Log: finished processing country.
    $this->logger->info($this->t('Finished processing country @uuid', ['@uuid' => $uuid]));

    // Set verified status.
    izi_urlalias_status_set_verified($object->getUuid(), $object->getLanguageCode(), $object->getRevisionHash());

  }

  /**
   * Constructs a human-readable URL alias for Country content.
   *
   * @param \Triquanta\IziTravel\DataType\CountryCityTranslation $object
   *   Country object to create the URL for.
   * @param string $type
   *   Data type to be used as URL suffix.
   *
   * @return string
   *   Translated human-readable URL.
   */
  protected function izi_urlalias_country_clean_url(CountryCityTranslation $object, string $type) {

    $name = _izi_urlalias_prepare_url_string($object->getName());
    $langcode = $object->getLanguageCode();
    $clean_url = '';

    switch ($type) {
      case 'all':
        $clean_url = $this->_izi_urlalias_translate_CountryCity_url('', 'tourguides-in', $name, $langcode);
        break;

      case 'museum':
        $clean_url = $this->_izi_urlalias_translate_CountryCity_url('', 'museum-tours-in', $name, $langcode);
        break;

      case 'tour':
        $clean_url = $this->_izi_urlalias_translate_CountryCity_url('', 'tours-in', $name, $langcode);
        break;

      case 'quest':
        $clean_url = $this->_izi_urlalias_translate_CountryCity_url('', 'quests-in', $name, $langcode);
        break;
    }

    return $clean_url;

    // This code is unreachable on purpose. By placing translated strings here
    // they will be picked up by potx but will not be executed. These strings are
    // used as variable in a t() call.
    // @see _izi_urlalias_CountryCity_url_translations()
    $this->t('tourguides-in');
    $this->t('museum-tours-in');
    $this->t('tours-in');
    $this->t('quests-in');
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
  protected function izi_urlalias_country_city_system_url($leading, $uuid, $type) {

    $segments[] = $leading;
    $segments[] = $uuid;
    if ($type != 'all') {
      $segments[] = $type;
    }
    return implode('/', $segments);
  }

}
