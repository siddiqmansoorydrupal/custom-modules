<?php

namespace Drupal\izi_apicontent;

use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Url;

/**
 * Service description.
 */
class LanguageService {

  /**
   * Helper function to get a list of content languages, ordered from most
   * preferred to least preferred.
   *
   * @return string[]
   *   An array of language codes.
   */
  public function get_preferred_content_languages() {
    $languages = &drupal_static(__FUNCTION__);

    if (!isset($languages)) {
      $languages = [];
      $fallback = $this->get_fallback_languages();

      // Check if language is given in a URL parameter.
      $preferred_language = $this->get_preferred_language();
      if ($preferred_language && $preferred_language != IZI_APICONTENT_LANGUAGE_ANY) {
        $languages[] = $preferred_language;
      }

      // Check if locale is given in a URL parameter.
      $preferred_locale = $this->get_preferred_language('locale');
      if ($preferred_locale && $preferred_locale != IZI_APICONTENT_LANGUAGE_ANY) {
        $languages[] = $preferred_locale;
      }

      // Finally, add the fallback languages and remove duplicates. Re-index with
      // array_values, otherwise it can't be used with list().
      $languages = array_values(array_unique(array_merge($languages, $fallback)));
    }

    return $languages;
  }

  /**
   * Helper function, converts constant to an array of fallback language codes.
   */
  public function get_fallback_languages() {
    static $fallback_languages;
    if (!isset($fallback_languages)) {
      $fallback_languages = explode(' ', IZI_APICONTENT_LANGUAGE_FALLBACK);
    }
    return $fallback_languages;
  }

  /**
   * Helper function to get the preferred content language from the URL.
   *
   * @todo (legacy) Do we need to sanitize the $_GET variable?
   *
   * @param string $parameter
   *   (optional) The type of language to get.
   *   - lang: content language (default).
   *   - locale: interface language.
   * @param bool $force
   *   (optional) By default this function returns the 'any language' placeholder
   *   IZI_APICONTENT_LANGUAGE_ANY if no url parameter is provided. Set this flag
   *   to TRUE to force returning a valid language code, even if the 'any
   *   language' placeholder is explicitly given.
   *
   * @return string
   *   A language code or IZI_APICONTENT_LANGUAGE_ANY.
   */
  public function get_preferred_language($parameter = 'lang', $force = FALSE, $uuid = NULL) {
    $id = $parameter;
    $id = $force ? $id . '-force' : $id;
    $id = (empty($uuid)) ? $id : $id . '-' . $uuid;
    $value = &drupal_static(__FUNCTION__ . $id);

    if (!isset($value)) {

      if ($parameter == 'lang') {
        // Check if the last url segment is a language code.
        // D7: request_uri();
        $request_uri = \Drupal::request()->getRequestUri();
        $request_uri = strtok($request_uri, "?#");
        $request_uri = trim($request_uri, "/");
        $args = explode('/', $request_uri);
        $last = end($args);
        $fallback_languages = $this->get_fallback_languages();
        if (in_array($last, $fallback_languages)) {
          $value = $last;
        }
        // Else, if the 'lang' query is available (legacy!), use it.
        elseif (isset($_GET['lang'])) {
          $value = $_GET[$parameter];
        }
        // If we get here, and the uuid is set, use content default language.
        elseif (!empty($uuid)) {
          $value = $this->get_content_language_from_current_url_or_object();
        }
      }
      elseif ($parameter == 'locale') {
        $value = $this->get_interface_language();
      }

      if (!isset($value)) {
        $value = IZI_APICONTENT_LANGUAGE_ANY;
      }

      // If force is true, the 'any language' placeholder is not good enough (for 'lang' only)
      if ($force && $value == IZI_APICONTENT_LANGUAGE_ANY) {
        $fallback_languages = $this->get_fallback_languages();
        $value = reset($fallback_languages);
      }
    }

    return $value;
  }

  /**
   * Gets the content language from the URL.
   *
   * Attempts to get the content language from the URL. If the last URL segment
   * does not contain a valid language code, the.
   *
   *
   * $fallback value will be used.
   *
   * @param string $fallback
   *   Language fallback value.
   *
   *   Return string
   *   Content language code. Fallback value if no language was found.
   */
  public function get_content_language_from_current_url_or_object($fallback = ''): bool|string {
    $path = \Drupal::service('path.current')->getPath();
    $current_path_segments = explode('/', $path);
    $last_segment = end($current_path_segments);
    if (in_array($last_segment, $this->get_fallback_languages())) {
      return $last_segment;
    }

    /** @var \Drupal\izi_apicontent\IziObjectService $izi_object_service */
    $izi_object_service = \Drupal::service('izi_apicontent.izi_object_service');
    $object = $izi_object_service->loadCurrentPageObject();
    if ($object) {
      // Get the object's content language.
      return $object->getLanguageCode();
    }

    // Return the fallback as a last-resort.
    return $fallback;
  }

  /**
   * @return string
   *   Current interface language: code as expected by the izi.TRAVEL API
   */
  public function get_interface_language() {
    $interface_lang = &drupal_static(__FUNCTION__);

    if (!isset($interface_lang)) {
      $language = \Drupal::languageManager()->getCurrentLanguage();
      $map = $this->languages_map();
      if (array_key_exists($language->getId(), $map)) {
        $interface_lang = $map[$language->getId()]['code'];
      }
      else {
        $interface_lang = $language->getId();
      }
    }

    return $interface_lang;
  }

  /**
   * @return array
   *   Map between ISO 639 language codes and izi.TRAVEL api specifics
   */
  public function languages_map() {
    return [
      'nb' => [
        'code' => 'no',
        'name' => 'Norwegian',
        'native' => 'Norsk',
      ],
      'pt-pt' => [
        'code' => 'pt',
        'name' => 'Portuguese',
        'native' => 'Português',
      ],
      'pt-br' => [
        'code' => 'pt',
        'name' => 'Portuguese',
        'native' => 'Português',
      ],
      'zh-hans' => [
        'code' => 'zh',
        'name' => 'Chinese',
        'native' => '中文',
      ],
    ];
  }

  /**
   * Gets the currently active interface language codes.
   *
   * @return array
   *   Array of interface languages in izi.TRAVEL API language codes.
   */
  public function izi_apicontent_get_active_interface_languages() {
    $languages = &drupal_static(__FUNCTION__);

    if (!isset($languages)) {
      $map = $this->languages_map();
      $installed_languages = \Drupal::languageManager()->getLanguages();
      foreach (array_keys($installed_languages) as $langcode) {
        if (array_key_exists($langcode, $map)) {
          $languages[] = $map[$langcode]['code'];
        }
        else {
          $languages[] = $langcode;
        }
      }
    }

    return $languages;
  }

  /**
   * @return string
   *   Current interface language: code as expected by the izi.TRAVEL API
   */
  public function izi_apicontent_get_interface_language() {
    $interface_lang = &drupal_static(__FUNCTION__);

    if (!isset($interface_lang)) {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $map = $this->languages_map();
      if (array_key_exists($language, $map)) {
        $interface_lang = $map[$language]['code'];
      }
      else {
        $interface_lang = $language;
      }
    }

    return $interface_lang;
  }

  /**
   * Prepares a language code list for a select form item with all languages.
   *
   * D7 provided a larger list of iso languages, we combine the list from D7, D9
   * and our local language map in this class. Priority Local, D9, D7.
   *
   * @param bool $native
   *   Indicated if native language names should be returned.
   *
   * @return array
   *   An array of language names, keyed by language code.
   */
  public function get_language_names($native = TRUE) {
    $language_names = &drupal_static(__FUNCTION__);

    if (!isset($language_names)) {

      $d9_languages = LanguageManager::getStandardLanguageList();

      // Get older D7 iso list.
      $apicontent_path = \Drupal::service('extension.list.module')
        ->getPath('izi_apicontent');
      $d7_languages = [];
      $d7_languages_path = $apicontent_path . '/data/iso.inc';
      if (file_exists($d7_languages_path)) {
        include_once $d7_languages_path;
        $d7_languages = d7_locale_get_predefined_list();
      }

      $language_list = array_merge($d7_languages, $d9_languages);

      // Add additional language name definitions to match the language
      // (country) codes coming in from the IZI Travel API.
      foreach ($this->languages_map() as $language) {
        $language_list[$language['code']] = [$language['name'], $language['native']];
      }

      foreach ($language_list as $key => $value) {
        $language_names['translated'][$key] = t($value[0]);
        $language_names['native'][$key] = $value[1] ?? $value[0];
      }

      // Add custom languages that are not part of the iso.inc definition.
      // $installed_languages = language_list();
      $installed_languages = \Drupal::languageManager()->getLanguages();
      $native_languages = \Drupal::languageManager()->getNativeLanguages();
      foreach ($installed_languages as $lang => $info) {
        if (!isset($language_names['native'][$lang])) {
          $native_language = $native_languages[$info->getId()];
          // $info->native;
          $language_names['native'][$lang] = $native_language->getName();
        }
        if (!isset($language_names['translated'][$lang])) {
          $language_names['translated'][$lang] = t($info->getName());
        }
      }
    }

    if (!$native) {
      return $language_names['translated'];
    }
    return $language_names['native'];
  }

  /**
   * Gets the content language from the URL.
   *
   * Attempts to get the content language from the URL. If the last URL segment
   * does not contain a valid language code, $fallback value will be used.
   *
   * @param string $fallback
   *   Language fallback value.
   *
   *   Return string
   *   Content language code. Fallback value if no language was found.
   *
   * @return mixed|string
   */
  public function izi_apicontent_get_content_language_from_url($fallback = '') {
    $current_path = Url::fromRoute('<current>')->toString();
    $current_path_segments = explode('/', $current_path);
    $last_segment = end($current_path_segments);
    if (in_array($last_segment, $this->get_fallback_languages())) {
      return $last_segment;
    }
    return $fallback;
  }

}
