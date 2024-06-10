<?php

namespace Drupal\izi_urlalias;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Drupal\izi_libizi\Libizi;
use Drupal\izi_urlalias\Plugin\QueueWorker\CityAlias;
use Drupal\izi_urlalias\Plugin\QueueWorker\CountryAlias;
use Drupal\izi_urlalias\Plugin\QueueWorker\TourAlias;
use Psr\Log\LoggerInterface;
use Triquanta\IziTravel\DataType\MultipleFormInterface;

/**
 * Service description.
 */
class Fetcher {

  use StringTranslationTrait;

  /**
   * Directory to store imported data.
   */
  public const DIRECTORY = 'private://izi_imports';

  /**
   * The izi_libizi.libizi service.
   *
   * @var \Drupal\izi_libizi\Libizi
   */
  protected $libizi;

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
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a Fetcher object.
   *
   * @param \Drupal\izi_libizi\Libizi $libizi
   *   The izi_libizi.libizi service.
   * @param \Drupal\izi_apicontent\LanguageService $language_service
   *   The izi_apicontent.language_service service.
   * @param \Drupal\izi_apicontent\IziObjectService $izi_object_service
   *   The izi_apicontent.izi_object_service service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(
    Libizi $libizi,
    LanguageService $language_service,
    IziObjectService $izi_object_service,
    QueueFactory $queue_factory,
    TranslationInterface $string_translation,
    FileSystemInterface $file_system,
    LoggerChannelFactoryInterface $logger
  ) {
    $this->libizi = $libizi;
    $this->languageService = $language_service;
    $this->iziObjectService = $izi_object_service;
    $this->queueFactory = $queue_factory;
    $this->stringTranslation = $string_translation;
    $this->fileSystem = $file_system;
    $this->logger = $logger->get('izi_urlalias');
  }

  /**
   * Get tours.
   *
   * @param $lang
   *   Language.
   * @param $offset
   *   Offset
   * @param $limit
   *   Limit
   *
   * @return int
   */
  public function getTours($lang, $offset, $limit): int {

    $queue_name = TourAlias::QUEUE_NAME;
    $queue = $this->queueFactory->get($queue_name);
    $objects = $this->fetchTours($lang, $offset, $limit);
    $count = count($objects);
    foreach ($objects as $object) {
      $queue->createItem($object);
      // $this->izi_store_izi_data_file($object, 'tour');
    }
    $this->logger->debug($this->t('Fetcher: Saved %count tours in %lang language to the queue.', [
      '%count' => $count,
      '%lang' => $lang,
    ]));
    return $count;
  }

  /**
   * Get cities.
   *
   * @param $lang
   *   Language.
   * @param $offset
   *   Offset
   * @param $limit
   *   Limit
   *
   * @return int
   */
  public function getCities($lang, $offset, $limit): int {

    $queue_name = CityAlias::QUEUE_NAME;
    $queue = $this->queueFactory->get($queue_name);
    $objects = $this->fetchCities($lang, $offset, $limit);
    $count = count($objects);
    foreach ($objects as $object) {
      $queue->createItem($object);
      // $this->izi_store_izi_data_file($object, 'city');
    }
    $this->logger->debug($this->t('Fetcher: Saved %count cities in %lang language to the queue.', [
      '%count' => $count,
      '%lang' => $lang,
    ]));
    return $count;
  }

  /**
   * @param $lang
   *   Language.
   * @param $offset
   *   Offset
   * @param $limit
   *   Limit
   *
   * @return int
   */
  public function getCountries($offset, $limit): int {

    $queue_name = CountryAlias::QUEUE_NAME;
    $queue = $this->queueFactory->get($queue_name);
    $objects = $this->fetchCountries($offset, $limit);
    $count = count($objects);
    foreach ($objects as $object) {
      $queue->createItem($object);
      // $this->izi_store_izi_data_file($object, 'country');
    }
    $this->logger->debug($this->t('Fetcher: Saved %count countries to the queue.', [
      '%count' => $count,
    ]));
    return $count;
  }

  /***************************************
   * Fetcher functions.
   ***************************************/
  protected function fetchCountries($offset, $limit) {
    // Load a number of objects.
    $izi_client = $this->libizi->getLibiziClient();
    $lang_codes = $this->languageService->get_preferred_content_languages();
    $request = $izi_client
      ->getCountries($lang_codes)
      ->setForm(MultipleFormInterface::FORM_COMPACT)
      ->setIncludes(['translations'])
      ->setOffset($offset)
      ->setLimit($limit);
    $objects = $request->execute();
    $count = count($objects);
    return $count ? $objects : [];
  }

  /**
   *
   */
  protected function fetchCities($lang_code, $offset, $limit) {
    // Load a number of objects.
    $izi_client = $this->libizi->getLibiziClient();
    $request = $izi_client
      ->getCities([$lang_code])
      ->setForm(MultipleFormInterface::FORM_COMPACT)
      ->setIncludes(['translations'])
      ->setOffset($offset)
      ->setLimit($limit);
    $objects = $request->execute();
    $count = count($objects);
    return $count ? $objects : [];
  }

  /**
   *
   */
  protected function fetchTours($lang_code, $offset, $limit, $search_string = '') {
    // Load a number of objects.
    $izi_client = $this->libizi->getLibiziClient();
    $request = $izi_client->search([$lang_code], $search_string)
      ->setTypes(['tour,museum,collection'])
      ->setIncludes(['children'])
      ->setForm(MultipleFormInterface::FORM_FULL)
      ->setLimit($limit)
      ->setOffset($offset);
    $objects = $request->execute();
    $count = count($objects);
    return $count ? $objects : [];
  }

}
