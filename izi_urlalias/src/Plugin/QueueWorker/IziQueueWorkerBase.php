<?php

namespace Drupal\izi_urlalias\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Drupal\izi_libizi\Libizi;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\IziTravel\DataType\CountryCityTranslation;

/**
 * Base class for queue workers.
 */
abstract class IziQueueWorkerBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The number of items that will be fetched from the API in one request.
   *
   * Staging API performance, in 1 second: approx. 30 compact items, 3 full
   * items with content.
   *
   * @var integer
   */
  const IZI_URLALIAS_FETCH_CHUNK_SIZE = 10;

  /**
   * The maximum time (in seconds) the cron worker is allowed to do it's thing.
   *
   * @var integer
   */
  // 4 min. Set cron at 5 minute
  const IZI_URLALIAS_CRON_TIME_LIMIT = 240;

  /**
   * The minimum time (in seconds) between two full two cron cycles.
   *
   * @var integer
   */
  // 6 hours
  const IZI_URLALIAS_CRON_INTERVAL = 21600;

  /**
   * Unique name of the queue for izi URL aliases tasks.
   *
   * @var string
   */
  const IZI_URLALIAS_QUEUE_NAME = 'izi_urlalias';

  /**
   * The time in seconds before old items and their aliases will deleted
   * permanently.
   *
   * @var int
   */
  const IZI_URLALIAS_DELETE_THRESHOLD = 86400;

  /**
   * The time in microseconds between API requests.
   *
   * @var int
   */
  const IZI_URLALIAS_API_SLEEP = 500;

  /**
   * Type name for the xmlsitemap elements (canonical, same Drupal & MTG
   * language).
   */
  const IZI_URLALIAS_XMLSITEMAP_TYPE = 'izi_urlalias';

  /**
   * Type name for the xmlsitemap elements (canonical, not the same Drupal &
   * MTG langs).
   */
  const IZI_URLALIAS_XMLSITEMAP_TYPE_OTHER_LANGUAGES = 'izi_urlalias_other_languages';

  /**
   * SERVICES.
   */

  /**
   * Lib Izi.
   *
   * @var \Drupal\izi_libizi\Libizi
   */
  protected Libizi $iziLibizi;

  /**
   * IZI language service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected LanguageService $iziLanguages;

  /**
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected IziObjectService $iziObjects;


  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Creator.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   *
   * @return \Drupal\izi_urlalias\Plugin\QueueWorker\IziQueueWorkerBase|static
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('izi_libizi.libizi'),
      $container->get('izi_apicontent.language_service'),
      $container->get('izi_apicontent.izi_object_service'),
      $container->get('queue'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   *
   */
  public function __construct(array $configuration,
  $plugin_id,
  $plugin_definition,
    Libizi $libizi,
    LanguageService $languages,
    IziObjectService $apiObjects,
    QueueFactory $queueFactory,
    LoggerChannelFactoryInterface $logger,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->iziLibizi = $libizi;
    $this->iziLanguages = $languages;
    $this->iziObjects = $apiObjects;
    $this->queueFactory = $queueFactory;
    $this->logger = $logger->get('izi_urlalias');
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Helper function to check if an uuid has an attached alias.
   */
  protected function izi_urlalias_uuid_has_alias($type, $uuid, $langcode) {
    $ret = FALSE;

    /** @var \Drupal\path_alias\AliasRepository $path_alias_repository */
    $path_storage = $this->entityTypeManager->getStorage('path_alias');

    $path = "$type/$uuid";
    $existing_alias = $path_storage->loadByProperties([
      'path' => $path,
      'langcode' => $langcode,
    ]);

    if (!empty($existing_alias)) {
      /** @var \Drupal\path_alias\Entity\PathAlias $path_alias */
      $path_alias = reset($existing_alias);
      return TRUE;
    }
    return $ret;
  }

  /**
   * Helper: Builds a CountryCity translation object.
   *
   * @param $name
   *   City name
   * @param $language
   *   City language code
   *
   * @return \Triquanta\IziTravel\DataType\CountryCityTranslation
   */
  protected function _izi_urlalias_build_country_city_translation($name, $language) {
    $data = (object) [
      'name' => $name,
      'language' => $language,
    ];
    $translation = new CountryCityTranslation();
    return $translation->createFromData($data);
  }

  /**
   * Helper to translate and construct a country or city url.
   *
   * @param string $leading
   *   (optional) Leading URL segment. In city URL's this is the country name.
   * @param string $prefix
   *   Country/city name prefix. Example 'tourguides-in'.
   * @param string $name
   *   Localized country or city name.
   * @param string $suffix
   *   URL suffix. Example 'all', 'tour'.
   * @param string $langcode
   *   Language code used to localize the $prefix string.
   *
   * @return string
   *   Localized country or city URL.
   */
  protected function _izi_urlalias_translate_CountryCity_url(string $leading, string $prefix, string $name, string $langcode): string {
    $langcode = izi_urlalias_izi_to_drupal_language($langcode);
    $prefix = t($prefix, [], ['langcode' => $langcode]);
    $name = _izi_urlalias_prepare_url_string($name);
    if (!empty($leading)) {
      $segments[] = _izi_urlalias_prepare_url_string($leading);
    }
    $segments[] = "$prefix-$name";

    return implode('/', $segments);
  }

}
