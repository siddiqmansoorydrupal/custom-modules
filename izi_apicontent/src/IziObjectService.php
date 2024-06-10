<?php

namespace Drupal\izi_apicontent;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\izi_libizi\Exception\IziLibiziNotFoundException;
use Drupal\izi_libizi\Libizi;
use GuzzleHttp\Exception\RequestException;
use Triquanta\IziTravel\DataType\Audio;
use Triquanta\IziTravel\DataType\CityInterface;
use Triquanta\IziTravel\DataType\CollectionInterface;
use Triquanta\IziTravel\DataType\CompactTouristAttractionInterface;
use Triquanta\IziTravel\DataType\CountryInterface;
use Triquanta\IziTravel\DataType\ExhibitInterface;
use Triquanta\IziTravel\DataType\FeaturedCityInterface;
use Triquanta\IziTravel\DataType\FeaturedContentCoverImageInterface;
use Triquanta\IziTravel\DataType\FeaturedContentImageInterface;
use Triquanta\IziTravel\DataType\FeaturedMtgObjectInterface;
use Triquanta\IziTravel\DataType\FeaturedMuseumInterface;
use Triquanta\IziTravel\DataType\FeaturedTourInterface;
use Triquanta\IziTravel\DataType\FullCollectionInterface;
use Triquanta\IziTravel\DataType\FullExhibitInterface;
use Triquanta\IziTravel\DataType\FullTouristAttractionInterface;
use Triquanta\IziTravel\DataType\Image;
use Triquanta\IziTravel\DataType\MediaInterface;
use Triquanta\IziTravel\DataType\MtgObjectInterface;
use Triquanta\IziTravel\DataType\MultipleFormInterface;
use Triquanta\IziTravel\DataType\MuseumInterface;
use Triquanta\IziTravel\DataType\PublisherInterface;
use Triquanta\IziTravel\DataType\TourInterface;
use Triquanta\IziTravel\DataType\TouristAttractionInterface;
use Triquanta\IziTravel\DataType\UuidInterface;
use Triquanta\IziTravel\DataType\Video;

/**
 * Service description.
 */
class IziObjectService {

  use LoggerChannelTrait;

  /**
   * The izi_libizi.libizi service.
   *
   * @var \Drupal\izi_libizi\Libizi
   */
  protected Libizi $libizi;

  /**
   * The izi_apicontent.language_service service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected LanguageService $language_service;

  protected RouteMatchInterface $route_match;

  /**
   * Constructs an IziObjectService object.
   *
   * @param \Drupal\izi_libizi\Libizi $libizi
   *   The izi_libizi.libizi service.
   * @param \Drupal\izi_apicontent\LanguageService $language_service
   *   The izi_apicontent.language_service service.
   */
  public function __construct(Libizi $libizi, LanguageService $language_service, RouteMatchInterface $route_match) {
    $this->libizi = $libizi;
    $this->language_service = $language_service;
    $this->route_match = $route_match;
  }

  /**
   * Loads an object from the Izi Travel API.
   *
   * Previously izi_apicontent_object_load.
   *
   * @param string $uuid
   *   The uuid retrieved from the URL.
   * @param string $type
   *   The type of object to retrieve, one of these 4 values:
   *   - mtg_object
   *   - publisher
   *   - country
   *   - city.
   * @param string $form
   *   The constant which defines the form of the data to be retrieved, either
   *   MultipleFormInterface::FORM_FULL or MultipleFormInterface::FORM_COMPACT.
   * @param string[] $includes
   *   An array of object components to request in the API call. See the API
   *   documentation for more info.
   *
   * @return \Triquanta\IziTravel\DataType\CityInterface|\Triquanta\IziTravel\DataType\CountryInterface|\Triquanta\IziTravel\DataType\PublisherInterface|\Triquanta\IziTravel\DataType\MtgObjectInterface
   *   A API object of the specified type and form.
   *
   * @throws \Exception
   */
  public function loadObject(
    string $uuid,
    string $type,
    string $form = MultipleFormInterface::FORM_FULL,
    array $includes = []
  ) {

    // Sometimes the UUID is empty. Detect this situation for further debugging.
    // @todo (legacy) Add check in libizi library to improve the API request integrity.
    if (empty($uuid)) {
      $this->getLogger('izi_apicontent')
        ->warning('Empty UUID detected for object of type @type.', ['@type' => $type]);
      return NULL;
    }

    // Add static caching for performance.
    $objects = &drupal_static(__FUNCTION__);
    // @todo we don't have the includes in Breadcrumb, only the uuid.
    $static_cache_key = $uuid . $form . serialize($includes);

    if (!isset($objects[$static_cache_key])) {
      // Get the current languages.
      $languages = $this->language_service->get_preferred_content_languages();

      // Unfortunately, the API design forces use to switch between methods based
      // on the type of object we need.
      $request = NULL;
      switch ($type) {
        case IZI_APICONTENT_TYPE_MTG_OBJECT:
          $includes = array_unique(
            array_merge(IZI_APICONTENT_TYPE_MTG_OBJECT_TYPES, $includes)
          );
          $request = $this->libizi->getLibiziClient()->getMtgObjectByUuid($languages, $uuid);
          break;

        case IZI_APICONTENT_TYPE_PUBLISHER:
          $request = $this->libizi->getLibiziClient()->getPublisherByUuid($languages, $uuid);
          break;

        case IZI_APICONTENT_TYPE_COUNTRY:
          $request = $this->libizi->getLibiziClient()->getCountryByUuid($languages, $uuid);
          break;

        case IZI_APICONTENT_TYPE_CITY:
          $request = $this->libizi->getLibiziClient()->getCityByUuid($languages, $uuid);
          break;
      }

      if ($request) {
        $result = $request
          ->setForm($form)
          ->setIncludes($includes)
          ->execute();
        $objects[$static_cache_key] = $result;
      }
    }

    if (isset($objects[$static_cache_key])) {
      return $objects[$static_cache_key];
    }
    else {
      // Something has gone wrong, maybe the $type parameter was incorrect?
      throw new \Exception(sprintf('Could not load an object of type %s with uuid %s.', $type, $uuid));
    }
  }

  /**
   * Loads multiple MTG objects from the API.
   *
   * @param string[] $uuids
   *   An array of uuids.
   * @param string $form
   *   The constant which defines the form of the data to be retrieved, either
   *   MultipleFormInterface::FORM_FULL or MultipleFormInterface::FORM_COMPACT.
   * @param string[] $includes
   *   An array of object components to request in the API call. See the API
   *   documentation for more info.
   *
   * @return \Triquanta\IziTravel\DataType\MtgObjectInterface[] An array of MTG objects.
   *   An array of MTG objects.
   *
   * @throws \Exception
   */
  public function izi_apicontent_mtg_object_load_multiple(array $uuids, $form = MultipleFormInterface::FORM_FULL, array $includes = []) {

    // Get the current languages.
    $languages = $this->language_service->get_preferred_content_languages();

    $includes += [
      'children',
      'collections',
    ];

    // To prevent API errors (HTTP 414 responses), load the objects in batches.
    $objects = [];
    $per_batch = 50;
    $number_of_uuids = count($uuids);

    for ($batch = 0; $batch * $per_batch < $number_of_uuids; $batch++) {
      $start_batch = $batch * $per_batch;
      $batch_uuids = array_slice($uuids, $start_batch, $per_batch);
      $batch_objects = $this->libizi->getLibiziClient()
        ->getMtgObjectsByUuids($languages, $batch_uuids)
        ->setForm($form)
        ->setIncludes($includes)
        ->execute();
      $objects = array_merge($objects, $batch_objects);
    }

    // When loading multiple MTG objects, trying to retrieve (part of) them from
    // static cache is too complex. However we can store them in the static cache
    // for the izi_apicontent_object_load() function, in case a single one of them
    // is requested again.
    $cache = &drupal_static('izi_apicontent_object_load');
    /** @var \Triquanta\IziTravel\DataType\MtgObjectInterface $object */
    foreach ($objects as $object) {
      $static_cache_key = crc32($object->getUuid() . $form . serialize($includes));
      $cache[$static_cache_key] = $object;
    }

    return $objects;
  }

  /**
   *
   */
  public function getCurrentPageUuid() {
    return $this->route_match->getParameter('uuid') ?? FALSE;
  }

  /**
   *
   */
  public function loadCurrentPageObject() {
    $uuid = $this->getCurrentPageUuid();
    if ($uuid) {
      $object = $this->loadObjectByUUID($uuid, IZI_APICONTENT_TYPE_MTG_OBJECT);
      if ($parent = $this->getParentObject($object)) {
        return $parent;
      }
      return $object;
    }
    return FALSE;
  }

  /**
   * Load object by uuid and type
   * if FullExhibitInterface || FullTouristAttractionInterface || FullCollectionInterface
   * returns parent
   *
   * @param string $uuid
   *   uuid.
   * @param string $type
   *   object type.
   *
   * @return \Triquanta\IziTravel\DataType\MtgObjectInterface MTG object.
   *   MTG object.
   *
   * @throws \Exception
   */
  public function loadObjectByUUID(string $uuid, string $type) {
    return $this->loadObject(
      $uuid,
      $type,
    );
  }

  /**
   *
   */
  public function getParentObject($object) {
    if ($object instanceof FullExhibitInterface ||
      $object instanceof FullTouristAttractionInterface ||
      $object instanceof FullCollectionInterface
    ) {
      // Overwrite the object with the parent.
      $parent_uuid = $object->getParentUuid();
      if ($parent_uuid) {
        try {
          return $this->loadObject($parent_uuid, IZI_APICONTENT_TYPE_MTG_OBJECT);
        }
        catch (IziLibiziNotFoundException $e) {
          // We don't launch an exception to avoid unnecessary 404 errors
          // caused because of breadcrumbs. See IZT-2114.
          $parent = NULL;
        }
      }
    }
    return FALSE;
  }

  /**
   * Helper function, gets a simplified type of an object, for internal use only.
   *
   * @param \Triquanta\IziTravel\DataType\CityInterface|\Triquanta\IziTravel\DataType\CountryInterface|\Triquanta\IziTravel\DataType\MtgObjectInterface|\Triquanta\IziTravel\DataType\PublisherInterface $object
   *
   * @return string
   *
   * @throws \Exception
   */
  public function izi_apicontent_get_object_type($object) {
    if ($object instanceof MtgObjectInterface) {
      return IZI_APICONTENT_TYPE_MTG_OBJECT;
    }
    if ($object instanceof PublisherInterface) {
      return IZI_APICONTENT_TYPE_PUBLISHER;
    }
    if ($object instanceof CountryInterface) {
      return IZI_APICONTENT_TYPE_COUNTRY;
    }
    if ($object instanceof CityInterface) {
      return IZI_APICONTENT_TYPE_CITY;
    }
    if ($object instanceof CityInterface) {
      return IZI_APICONTENT_TYPE_CITY;
    }

    throw new \Exception('Could not determine internal type.');
  }

  /**
   * Helper function, gets a simplified sub type of an object, for internal use only.
   *
   * @param \Triquanta\IziTravel\DataType\CityInterface|\Triquanta\IziTravel\DataType\CountryInterface|\Triquanta\IziTravel\DataType\MtgObjectInterface|\Triquanta\IziTravel\DataType\PublisherInterface $object
   *
   * @return bool
   */
  public function izi_apicontent_get_sub_type($object) {
    if ($object instanceof MuseumInterface || $object instanceof FeaturedMuseumInterface) {
      return IZI_APICONTENT_SUB_TYPE_MUSEUM;
    }
    if ($object instanceof TourInterface || $object instanceof FeaturedTourInterface) {
      return IZI_APICONTENT_SUB_TYPE_TOUR;
    }
    if ($object instanceof TouristAttractionInterface) {
      return IZI_APICONTENT_SUB_TYPE_TOURIST_ATTRACTION;
    }
    if ($object instanceof ExhibitInterface) {
      return IZI_APICONTENT_SUB_TYPE_EXHIBIT;
    }
    if ($object instanceof CollectionInterface) {
      return IZI_APICONTENT_SUB_TYPE_COLLECTION;
    }
    if ($object instanceof PublisherInterface) {
      return IZI_APICONTENT_TYPE_PUBLISHER;
    }
    if ($object instanceof CountryInterface) {
      return IZI_APICONTENT_TYPE_COUNTRY;
    }
    if ($object instanceof CityInterface || $object instanceof FeaturedCityInterface) {
      return IZI_APICONTENT_TYPE_CITY;
    }

    return FALSE;
  }

  /**
   * Helper function, returns a list of all the defined CONSTANT subtypes.
   *
   * @return array
   */
  public function izi_apicontent_get_sub_types() {
    $subtypes = [
      IZI_APICONTENT_SUB_TYPE_MUSEUM,
      IZI_APICONTENT_SUB_TYPE_TOUR,
      IZI_APICONTENT_SUB_TYPE_TOURIST_ATTRACTION,
      IZI_APICONTENT_SUB_TYPE_EXHIBIT,
      IZI_APICONTENT_SUB_TYPE_COLLECTION,
      IZI_APICONTENT_TYPE_PUBLISHER,
      IZI_APICONTENT_TYPE_COUNTRY,
      IZI_APICONTENT_TYPE_CITY,
    ];

    return $subtypes;
  }

  /**
   * @param string|\Triquanta\IziTravel\DataType\UuidInterface $object
   *   The uuid of an API content object or the object itself.
   * @param string $type
   *   The type of object.
   * @param string $language
   *   A language code for the desired content language. Defaults to the current
   *   preferred content language.
   * @return string
   *   A url for an API object page.
   * @throws \Exception
   */
  public function izi_apicontent_path($object, $type = IZI_APICONTENT_TYPE_MTG_OBJECT, $language = NULL) {
    if ($object instanceof UuidInterface) {
      $uuid = $object->getUuid();
    }
    elseif (is_string($object)) {
      $uuid = $object;
    }
    else {
      $object_type = is_object($object) ? get_class($object) : gettype($object);
      throw new \InvalidArgumentException(sprintf('$object must be an MTG object UUID or an instance of \Triquanta\IziTravel\DataType\UuidInterface, but %s given.', $object_type));
    }

    // Make sure we return a path to a language that is actually available.
    if (!$language && ($type == IZI_APICONTENT_TYPE_MTG_OBJECT)) {
      if (is_string($object)) {
        $object = $this->loadObject($object, $type);
      }
      $available_languages = $object->getAvailableLanguageCodes();
      $content_languages = $this->language_service->get_preferred_content_languages();
      // Choose the first one as fallback.
      $language = $available_languages[0];
      // See if there is a better match.
      foreach ($content_languages as $language_code) {
        if (in_array($language_code, $available_languages)) {
          $language = $language_code;
          // Found it, we can stop now.
          break;
        }
      }
    }
    switch ($type) {
      case IZI_APICONTENT_TYPE_MTG_OBJECT:
        $path = '/browse/' . $uuid . '/' . $language;
        break;

      case IZI_APICONTENT_TYPE_PUBLISHER:
        if ($language) {
          $path = '/browse/publishers/' . $uuid . '/' . $language;
        }
        else {
          $path = '/browse/publishers/' . $uuid;
        }
        break;

      case IZI_APICONTENT_TYPE_COUNTRY:
        $path = '/country/' . $uuid;
        break;

      case IZI_APICONTENT_TYPE_CITY:
        $path = '/city/' . $uuid;
        break;
    }
    if (!isset($path)) {
      throw new \Exception('Cannot create path.');
    }
    return $path;
  }

  /**
   * Creates Drupal::Url object from IZI Object.
   *
   * Creating Drupal links requires Url objects, this function is an alternative
   * to izi_apicontent_url which returns Url objects using D8/9 routes.
   *
   * @param string|\Triquanta\IziTravel\DataType\UuidInterface $object
   *   The uuid of an API content object or the object itself.
   * @param string $type
   *   The type of object.
   * @param string $language
   *   A language code for the desired content language. Defaults to the current
   *   preferred content language.
   *
   * @return \Drupal\Core\Url
   *   A Drupal Url for an API object page.
   *
   * @throws \Exception
   */
  public function izi_apicontent_drupal_url($object, $type = IZI_APICONTENT_TYPE_MTG_OBJECT, $language = NULL): Url {
    if ($object instanceof UuidInterface) {
      $uuid = $object->getUuid();
    }
    elseif (is_string($object)) {
      $uuid = $object;
    }
    else {
      $object_type = is_object($object) ? get_class($object) : gettype($object);
      throw new \InvalidArgumentException(sprintf('$object must be an MTG object UUID or an instance of \Triquanta\IziTravel\DataType\UuidInterface, but %s given.', $object_type));
    }

    // Make sure we return a path to a language that is actually available.
    if (!$language && ($type == IZI_APICONTENT_TYPE_MTG_OBJECT)) {
      if (is_string($object)) {
        $object = $this->loadObject($object, $type);
      }
      $available_languages = $object->getAvailableLanguageCodes();
      $content_languages = $this->language_service->get_preferred_content_languages();
      // Choose the first one as fallback.
      $language = $available_languages[0];
      // See if there is a better match.
      foreach ($content_languages as $language_code) {
        if (in_array($language_code, $available_languages)) {
          $language = $language_code;
          // Found it, we can stop now.
          break;
        }
      }
    }
    switch ($type) {
      case IZI_APICONTENT_TYPE_MTG_OBJECT:
        $url = Url::fromRoute('izi_apicontent.browse', [
          'country' => $uuid,
          'language' => $language,
        ]);
        break;

      case IZI_APICONTENT_TYPE_PUBLISHER:
        $url = Url::fromRoute('izi_apicontent.browse.publishers', [
          'country' => $uuid,
          'language' => $language,
        ]);
        break;

      case IZI_APICONTENT_TYPE_COUNTRY:
        $url = Url::fromRoute('izi_search.country', [
          'country' => $uuid,
        ]);
        break;

      case IZI_APICONTENT_TYPE_CITY:
        $url = Url::fromRoute('izi_search.city', [
          'city' => $uuid,
        ]);
        break;
    }
    if (!isset($url)) {
      throw new \Exception('Cannot create path.');
    }
    return $url;
  }

  /**
   * Returns the raw object URL without content language.
   *
   * @param \Triquanta\IziTravel\DataType\UuidInterface $object
   *   The API content object.
   * @param string $type
   *   The type of object.
   *
   * @return string
   *
   * @throws \Exception When $object has wrong type or $type is now supported.
   */
  public function izi_apicontent_path_without_language($object, $type = IZI_APICONTENT_TYPE_MTG_OBJECT) {

    if ($object instanceof UuidInterface) {
      $uuid = $object->getUuid();
    }
    else {
      throw new \InvalidArgumentException('$object must be an MTG object UUID or an instance of \Triquanta\IziTravel\DataType\UuidInterface.');
    }

    switch ($type) {
      case IZI_APICONTENT_TYPE_MTG_OBJECT:
        $path = '/browse/' . $uuid;
        break;

      case IZI_APICONTENT_TYPE_PUBLISHER:
        $path = '/browse/publishers/' . $uuid;
        break;

      case IZI_APICONTENT_TYPE_COUNTRY:
        $path = '/country/' . $uuid;
        break;

      case IZI_APICONTENT_TYPE_CITY:
        $path = '/city/' . $uuid;
        break;
    }

    if (!isset($path)) {
      throw new \Exception('Cannot create path.');
    }

    return $path;
  }

  /**
   * Builds the url of a media object.
   *
   * @param \Triquanta\IziTravel\DataType\MediaInterface|\Triquanta\IziTravel\DataType\FeaturedContentCoverImageInterface $media
   *   A media or featured content image object.
   * @param \Triquanta\IziTravel\DataType\MtgObjectInterface|\Triquanta\IziTravel\DataType\PublisherInterface $parent
   *   The parent MTG object or Publisher object, needed to retrieve the publisher ID (except for featured content)
   * @param array $options
   *   An array containing options. Currently, only the 'size' key is supported.
   *
   * @return string
   *   The full url of a media asset.
   *
   * @throws \Exception
   */
  public function izi_apicontent_media_url(UuidInterface $media, $parent = NULL, array $options = []) {
    $base_urls = [];
    // $environment = variable_get('izi_libizi_environment', IZI_LIBIZI_ENVIRONMENT_PRODUCTION);
    $environment = $this->libizi->getCurrentEnvironment();

    $media_uuid = $media->getUuid();

    if ($parent instanceof MtgObjectInterface || $parent instanceof PublisherInterface) {
      $base_urls = [
        // IZI_LIBIZI_ENVIRONMENT_TESTING => 'http://media.dev.izi.travel',
        // IZI_LIBIZI_ENVIRONMENT_STAGING => 'https://media.stage.izi.travel',
        // IZI_LIBIZI_ENVIRONMENT_PRODUCTION => 'https://media.izi.travel',
        'test' => 'http://media.dev.izi.travel',
        'stage' => 'https://media.stage.izi.travel',
        'prod' => 'https://media.izi.travel',
      ];

      $content_provider = $parent->getContentProvider()->getUuid();
    }

    if ($media instanceof FeaturedContentCoverImageInterface || $media instanceof FeaturedContentImageInterface) {
      // The original variant is used for featured content.
      $base_urls = [
        // IZI_LIBIZI_ENVIRONMENT_TESTING => 'http://media.dev.izi.travel',
        // IZI_LIBIZI_ENVIRONMENT_STAGING => 'https://media.stage.izi.travel',
        // IZI_LIBIZI_ENVIRONMENT_PRODUCTION => 'https://media.izi.travel',
        'test' => 'http://media.dev.izi.travel',
        'stage' => 'https://media.stage.izi.travel',
        'prod' => 'https://media.izi.travel',
      ];

      $content_provider = 'featured';
    }
    if (!isset($content_provider)) {
      throw new \Exception('Could not generate media URL. Wrong media or parent object type.');
    }

    $base_url = $base_urls[$environment];

    $extension = '';
    $suffix = '';

    // Merge in defaults.
    $options += [
      'size' => '800x600',
    ];

    if ($media instanceof Audio) {
      $extension = '.m4a';
    }

    elseif ($media instanceof Image) {
      $extension = '.jpg';
      if ($media->getType() == 'story') {
        $suffix = '_' . $options['size'];
      }
      elseif ($media->getType() == 'brand_logo') {
        $extension = '.png';
      }
      elseif ($media->getType() == 'sponsor_logo') {
        $extension = '.png';
      }
    }

    elseif ($media instanceof Video) {
      $extension = '.mp4';
    }

    elseif ($media instanceof MediaInterface) {
      throw new \Exception('Could not generate media URL.');
    }

    return "{$base_url}/{$content_provider}/{$media_uuid}{$suffix}{$extension}";
  }

  /**
   * Builds the URL fragment for the object as child element.
   *
   * @param string|\Triquanta\IziTravel\DataType\UuidInterface $object
   *   The uuid of an API content object or the object itself.
   * @param string $type
   *   The type of object.
   *
   * @return string $language
   *   The content language
   *
   * @return string
   *  URL fragment string.
   */
  public function izi_apicontent_fragment($object, $type = IZI_APICONTENT_TYPE_MTG_OBJECT, $language = NULL) {
    $path = $this->izi_apicontent_path($object, $type, $language);
    $url = Url::fromUri("base:/{$path}")
      ->toString();
    return $url;
  }

  /**
   * Helper function to get the correct Content object from an array of
   * content objects.
   *
   * @param \Triquanta\IziTravel\DataType\ContentInterface[] $content_array
   *   The array of content items from the API object.
   * @param string $language
   *   The language to look for. If omitted, the language will be selected based
   *   on the current URL.
   *
   * @return \Triquanta\IziTravel\DataType\ContentInterface|null
   *   The content object in the most appropriate language.
   */
  public function get_object_language_content($content_array, $language = NULL) {
    $fallback = [];

    if (!$language) {
      $language = $this->language_service->get_preferred_language();
    }

    $languages = $this->language_service->get_preferred_content_languages();
    [$preferred_language, $fallback_first, $fallback_second] = $languages;

    foreach ($content_array as $index => $content) {
      $content_language = $content->getLanguageCode();

      if ($language && $content_language == $preferred_language) {
        // We found it. No further processing needed.
        return $content;
      }

      // Store the second choice fallback in case we don't find a better match.
      if ($content_language == $fallback_first) {
        $fallback[1] = $content;
      }

      // Store the third choice fallback in case we don't find a better match.
      if ($content_language == $fallback_second) {
        $fallback[2] = $content;
      }

      // Store the first content item, no matter what language it's in, just in
      // case we don't find a better match.
      if (empty($fallback[3])) {
        $fallback[3] = $content;
      }
    }

    // Return the most appropriate fallback option.
    if (!empty($fallback)) {
      ksort($fallback);
      return reset($fallback);
    }

    // If we arrive here, we found nothing. Shouldn't happen.
    return NULL;
  }

  /**
   * Returns the localized country name.
   *
   * Will return the country name in the preferred language or fall back to the
   * first available one.
   *
   * @param $uuid
   *   UUID of the country.
   * @param null|string $selected_language
   *   (option) Language code for the localization. Will override the preferred
   *   language selection.
   *
   * @return string|array
   *   Localized country name or array of available country names if the language
   *   fallback fails.
   *
   * @throws \Exception
   */
  public function getCountryNameByUuid($uuid, $selected_language = NULL) {

    $country_name_cid = 'izi_apicontent_country_name:' . $uuid;
    // $cache = cache_get($country_name_cid);
    $cache = \Drupal::cache()
      ->get($country_name_cid);
    $data = NULL;
    if ($cache) {
      $country_names = unserialize($cache->data);
    }
    else {
      $country = $this->loadObject(
        $uuid,
        IZI_APICONTENT_TYPE_COUNTRY,
        MultipleFormInterface::FORM_COMPACT,
        ['translations']
      );
      $country_names = [];
      foreach ($country->getTranslations() as $translation) {
        $country_names[$translation->getLanguageCode()] = $translation->getName();
      }
      // cache_set($country_name_cid, serialize($country_names), 'cache', time() + 3600 * 24);.
      \Drupal::cache()
        ->set($country_name_cid, serialize($country_names), time() + 3600 * 24, ['cache']);
    }

    if (isset($selected_language)) {
      return $country_names[$selected_language] ?? $country_names['en'];
    }

    $languages = $this->language_service->get_preferred_content_languages();
    foreach ($languages as $language) {
      if (isset($country_names[$language])) {
        return $country_names[$language];
      }
    }
    return reset($country_names);
  }

  /**
   * Returns the localized city name.
   *
   * Will return the country name in the preferred language or fall back to the
   * first available one.
   *
   * @param $uuid
   *   UUID of the city.
   * @param null $selected_language
   *   (option) Language code for the localization. Will override the preferred
   *   language selection.
   *
   * @return string|array
   *   Localized city name or array of available city names if the language
   *   fallback fails.
   *
   * @throws \Exception
   */
  public function getCityNameByUuid($uuid, $selected_language = NULL) {
    $city_name_cid = 'izi_apicontent_city_name:' . $uuid;
    // $cache = cache_get($city_name_cid);
    $cache = \Drupal::cache()
      ->get($city_name_cid);

    if ($cache) {
      $city_names = unserialize($cache->data);
    }
    else {
      $city = $this->loadObject(
        $uuid,
        IZI_APICONTENT_TYPE_CITY,
        MultipleFormInterface::FORM_COMPACT,
        ['translations']
      );
      if (empty($city)) {
        return '';
      }

      $city_names = [];
      foreach ($city->getTranslations() as $translation) {
        $city_names[$translation->getLanguageCode()] = $translation->getName();
      }
      // cache_set($city_name_cid, serialize($city_names), 'cache', time() + 3600 * 24);.
      \Drupal::cache()
        ->set($city_name_cid, serialize($city_names), time() + 3600 * 24, ['cache']);
    }

    if (isset($selected_language)) {
      return $city_names[$selected_language] ?? $city_names['en'];
    }

    $languages = $this->language_service->get_preferred_content_languages();
    foreach ($languages as $language) {
      if (isset($city_names[$language])) {
        return $city_names[$language];
      }
    }
    return reset($city_names);
  }

  /**
   * Helper function to check if current object is a tourist attraction.
   *
   * @param $object
   *
   * @return bool
   */
  public function _is_tourist_attraction($object) {
    return ($object instanceof CompactTouristAttractionInterface);
  }

  /**
   * Helper function to check if current object is hidden.
   *
   * @param $object
   *
   * @return bool
   */
  public function _is_hidden($object) {
    return !($object->isHidden());
  }

  /**
   * @param string $uuid
   *   The publisher's uuid of which to retrieve the content.
   * @return string[]
   *   An array of language codes.
   * @throws \Exception
   */
  public function izi_apicontent_get_publisher_content_languages(string $uuid) {
    return $this->libizi->getLibiziClient()
      ->getPublisherChildrenLanguagesByUuid($this->language_service->get_preferred_content_languages(), $uuid)
      ->execute();
  }

  /**
   * @param string $uuid
   *   The publisher's uuid of which to retrieve the content.
   * @param int $limit
   *   The number of objects to retrieve.
   * @param int $offset
   *   The number of items to skip.
   * @param string[] $languages
   *   An array of language codes to filter the content by.
   * @return \Triquanta\IziTravel\DataType\CompactMtgObjectInterface[]
   *   An array of MTG objects in compact form.
   * @throws \Exception
   */
  public function izi_apicontent_publisher_content_load($uuid, $limit, $offset, $languages) {
    $content = $this->libizi->getLibiziClient()
      ->getPublisherChildrenByUuid($this->language_service->get_preferred_content_languages(), $uuid)
      ->setForm(MultipleFormInterface::FORM_COMPACT)
      ->setIncludes(['country', 'city', 'translations'])
      ->setLanguageCodes($languages)
      ->setLimit($limit)
      ->setOffset($offset)
      ->execute();
    return $content;
  }

  /**
   * Get the fist address: street name for $location.
   *
   * @param $location
   *
   * @return mixed|string
   */
  public function izi_apicontent_openstreet_api_reverse_geocode_street_name($location) {
    $street_name = '';
    $addressdata = $this->izi_apicontent_openstreet_api_reverse_geocode($location);
    if ($addressdata) {
      $street_name = $addressdata['display_name'];
    }
    return $street_name;
  }

  /**
   * Get the openstreet maps object for a location.
   *
   * @param $location
   *
   * @return mixed|string
   */
  private function izi_apicontent_openstreet_api_reverse_geocode($location) {
    $lat = Xss::filter($location->getlatitude());
    $lon = Xss::filter($location->getLongitude());
    if (empty($lat) || empty($lon)) {
      return '';
    }
    $lang = \Drupal::service('izi_apicontent.language_service')
      ->get_interface_language();
    $url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&accept-language=' . $lang . '&lat=' . $lat . '&lon=' . $lon;

    try {
      $options = [
        'context' => stream_context_create([
          'ssl' => [
            'verify_peer' => FALSE,
            'verify_peer_name' => FALSE,
          ],
        ]),
      ];
      $response = \Drupal::httpClient()->get($url, $options);

      if (!empty($response->getBody())) {
        if ($response->getReasonPhrase() != 'OK') {
          throw new \Exception('No results returned by openstreet maps api (reverse geocoder).');
        }
        return json_decode($response->getBody(), TRUE);
      }
    }
    catch (RequestException $e) {
      watchdog_exception('izi_apicontent', $e);
    }
    catch (\Exception $e) {
      watchdog_exception('izi_apicontent', $e);
    }
    return '';
  }

  /**
   * @param string|\Triquanta\IziTravel\DataType\UuidInterface $object
   *   The uuid of an API content object or the object itself.
   * @param string $type
   *   The type of object.
   * @param mixed[] $options
   *   An array of options as accepted by the url() function.
   * @param string|null $language
   *   The language code or null.
   *
   * @return string
   *   A url for an API object page.
   */
  public function izi_apicontent_url($object, $type = IZI_APICONTENT_TYPE_MTG_OBJECT, $options = [], $language = NULL) {
    $path = $this->izi_apicontent_path($object, $type, $language);
    $alias = \Drupal::service('path_alias.manager')
      ->getAliasByPath($path);
    $langcode = $this->language_service->get_interface_language();
    $url = Url::fromUri("base:{$langcode}{$alias}", $options);
    return $url->toString();
  }

  /**
   * @param string $text
   *   The link text.
   * @param string|\Triquanta\IziTravel\DataType\UuidInterface $object
   *   The uuid of an API content object or the object itself.
   * @param string $type
   *   The type of object.
   * @param mixed[] $options
   *   An array of options as accepted by the url() function.
   * @return string
   *   A link for an API object page.
   */
  public function izi_apicontent_link($text, $object, $type = IZI_APICONTENT_TYPE_MTG_OBJECT, $options = [], $language = NULL) {
    $path = $this->izi_apicontent_path($object, $type, $language);
    $link = Link::fromTextAndUrl($text, Url::fromUri("internal:{$path}", $options));
    return $link->toString();
  }

  /**
   * Get featured contents from API. They will be shown in homepage, search and 404.
   */
  public function izi_apicontent_home_content_load() {
    // Add static caching for performance.
    // The language param doesn't matter about static cache, because we won't
    // request featured content in different languages in the same browser request.
    $content = &drupal_static(__FUNCTION__);

    if (empty($content)) {
      $langcode = \Drupal::service('izi_apicontent.language_service')
        ->get_interface_language();

      // Add a Drupal cache layer to avoid many API requests.
      $cid = 'featured_content_api_' . $langcode;
      $cache = \Drupal::cache()->get($cid);
      if ($cache) {
        $content = $cache->data;
      }
      else {
        // Get the featured content from the API.
        $content = $this->libizi->getLibiziClient()
          ->getFeaturedContent([$langcode])
          ->execute();
        \Drupal::cache()->set($cid, $content, time() + 36000);
      }
    }

    return $content;
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
  public function izi_apicontent_build_featured_mtg_object(FeaturedMtgObjectInterface $featured_item, $index = 0) {
    $build = [
      '#theme' => 'izi_featured_mtg_object',
    ];
    $build['#title'] = Xss::filter($featured_item->getName());
    $build['#title_truncted'] = Unicode::truncate($build['#title'], 75);

    $build['#url'] = Url::fromUri("internal:{$this->izi_apicontent_path($featured_item)}");

    $images = $featured_item->getImages();
    if (count($images)) {
      $image = reset($images);
      $build['#image_url'] = $this->izi_apicontent_media_url($image);
    }
    else {
      $build['#image_url'] = base_path()
      . \Drupal::service('extension.list.module')->getPath('izi_apicontent')
        . '/img/frontpage-placeholder.jpg';
      // $build['#image_url'] = '/' . drupal_get_path('module', 'izi_apicontent') . '/img/frontpage-placeholder.jpg';
    }

    $country_uuid = $featured_item->getCountryUuid();
    if (!empty($country_uuid)) {
      $build['#country_link'] = $this->izi_apicontent_link($this->izi_apicontent_get_country_name($country_uuid), $country_uuid, IZI_APICONTENT_TYPE_COUNTRY, ['attributes' => ['class' => 'featured-main-item-country']]);
    }
    $city_uuid = $featured_item->getCityUuid();
    if (!empty($city_uuid)) {
      $build['#city_link'] = $this->izi_apicontent_link($this->izi_apicontent_get_city_name($city_uuid), $city_uuid, IZI_APICONTENT_TYPE_CITY, ['attributes' => ['class' => 'featured-main-item-city']]);
    }
    else {
      $build['#city_link'] = '';
    }

    $build['#object_type'] = $this->izi_apicontent_get_sub_type($featured_item);

    $mtgcontent_compact = $this->loadObject($featured_item->getUuid(), IZI_APICONTENT_TYPE_MTG_OBJECT, MultipleFormInterface::FORM_COMPACT);

    $publisher = $mtgcontent_compact->getPublisher();
    if (!empty($publisher)) {
      $publisher_uuid = $publisher->getUuid();

      if (!empty($publisher_uuid)) {
        $cp_url = $this->izi_apicontent_path($publisher->getUuid(), IZI_APICONTENT_TYPE_PUBLISHER);
        $link = Link::fromTextAndUrl($publisher->getTitle(), Url::fromUri("internal:/{$cp_url}"));
        // l($publisher->getTitle(), $cp_url);.
        $content_provider = t('by') . ' ' . $link->toString();

        $build['#content_provider'] = $content_provider;
      }
    }

    if (empty($build['#content_provider'])) {
      $build['#content_provider'] = '';
    }

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
  public function _izi_apicontent_get_home_fallback_positions($count) {
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
   * Returns the localized country name.
   *
   * Will return the country name in the preferred language or fall back to the
   * first available one.
   *
   * @param $uuid
   *   UUID of the country.
   * @param null|string $selected_language
   *   (option) Language code for the localization. Will override the preferred
   *   language selection.
   *
   * @return string|array
   *   Localized country name or array of available country names if the language
   *   fallback fails.
   *
   * @throws \Exception
   */
  public function izi_apicontent_get_country_name($uuid, $selected_language = NULL) {
    $country_name_cid = 'izi_apicontent_country_name:' . $uuid;
    $cache = \Drupal::cache()->get($country_name_cid);

    if ($cache) {
      $country_names = unserialize($cache->data);
    }
    else {
      $country = $this->loadObject($uuid, IZI_APICONTENT_TYPE_COUNTRY, MultipleFormInterface::FORM_COMPACT, ['translations']);
      $country_names = [];
      foreach ($country->getTranslations() as $translation) {
        $country_names[$translation->getLanguageCode()] = $translation->getName();
      }
      \Drupal::cache()->set($country_name_cid, serialize($country_names), time() + 3600 * 24);
    }

    if (isset($selected_language)) {
      return $country_names[$selected_language] ?? $country_names['en'];
    }

    $languages = $this->language_service->get_preferred_content_languages();
    foreach ($languages as $language) {
      if (isset($country_names[$language])) {
        return $country_names[$language];
      }
    }
    return reset($country_names);
  }

  /**
   * Returns the localized city name.
   *
   * Will return the country name in the preferred language or fall back to the
   * first available one.
   *
   * @param $uuid
   *   UUID of the city.
   * @param null $selected_language
   *   (option) Language code for the localization. Will override the preferred
   *   language selection.
   *
   * @return string|array
   *   Localized city name or array of available city names if the language
   *   fallback fails.
   *
   * @throws \Exception
   */
  public function izi_apicontent_get_city_name($uuid, $selected_language = NULL) {
    $city_name_cid = 'izi_apicontent_city_name:' . $uuid;
    $cache = \Drupal::cache()->get($city_name_cid);

    if ($cache) {
      $city_names = unserialize($cache->data);
    }
    else {
      $city = $this->loadObject($uuid, IZI_APICONTENT_TYPE_CITY, MultipleFormInterface::FORM_COMPACT, ['translations']);
      if (empty($city)) {
        return '';
      }

      $city_names = [];
      foreach ($city->getTranslations() as $translation) {
        $city_names[$translation->getLanguageCode()] = $translation->getName();
      }
      \Drupal::cache()->set($city_name_cid, serialize($city_names), time() + 3600 * 24);
    }

    if (isset($selected_language)) {
      return $city_names[$selected_language] ?? $city_names['en'];
    }

    $languages = $this->language_service->get_preferred_content_languages();
    foreach ($languages as $language) {
      if (isset($city_names[$language])) {
        return $city_names[$language];
      }
    }
    return reset($city_names);
  }

  /**
   *
   */
  public function getChildShareUrl($full_object, $parent_object, $query_param = NULL) {
    $site = \Drupal::request()->getSchemeAndHttpHost();
    $share_url = $this->izi_apicontent_url(
      $parent_object->getUuid(),
      IZI_APICONTENT_TYPE_MTG_OBJECT,
      [
        'fragment' => $this->izi_apicontent_fragment(
          $full_object->getUuid(),
          IZI_APICONTENT_TYPE_MTG_OBJECT,
          $parent_object->getLanguageCode()
        ),
        'query' => $query_param,
      ]
      );
    return $site . $share_url;
  }

}
