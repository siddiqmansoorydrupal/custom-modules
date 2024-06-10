<?php

namespace Drupal\izi_urlalias\Commands;

use Drupal\Core\Queue\QueueFactory;
use Drupal\izi_apicontent\IziObjectService;
use Drupal\izi_apicontent\LanguageService;
use Drupal\izi_libizi\Libizi;
use Drupal\izi_urlalias\Plugin\QueueWorker\CityAlias;
use Drupal\izi_urlalias\Plugin\QueueWorker\CityFetcher;
use Drupal\izi_urlalias\Plugin\QueueWorker\CountryAlias;
use Drupal\izi_urlalias\Plugin\QueueWorker\CountryFetcher;
use Drupal\izi_urlalias\Plugin\QueueWorker\TourAlias;
use Drupal\izi_urlalias\Plugin\QueueWorker\TourFetcher;
use Drush\Commands\DrushCommands;
use Triquanta\IziTravel\DataType\MultipleFormInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class IziUrlaliasCommands extends DrushCommands {

  const IZI_URLALIAS_SLEEP = 200;
  const IZI_URLALIAS_CHUNK = 100;
  const IZI_URLALIAS_OBJ_CHUNK = 50;

  /**
   * Queue Factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Lib Izi.
   *
   * @var \Drupal\izi_libizi\Libizi
   */
  protected $iziLibizi;

  /**
   * IZI language service.
   *
   * @var \Drupal\izi_apicontent\LanguageService
   */
  protected $iziLanguages;

  /**
   * @var \Drupal\izi_apicontent\IziObjectService
   */
  protected $iziObjects;

  /**
   *
   */
  public function __construct(
    QueueFactory $queue_factory,
    Libizi $libizi,
    LanguageService $languages,
    IziObjectService $apiObjects,
  ) {
    parent::__construct();
    $this->queueFactory = $queue_factory;
    $this->iziLibizi = $libizi;
    $this->iziLanguages = $languages;
    $this->iziObjects = $apiObjects;
  }

  /**
   * Trigger fetch & process on all alias queues.
   *
   * @usage izi_start_sync
   *  Start fetching object aliases
   * @command izi_urlalias:izi_start_sync
   * @aliases izi_start_sync, izi-start-sync
   */
  public function startSync() {

    if (!$this->_queues_empty()) {
      $this->logger->error("Queues not empty, wait for queues to empty before starting queues again.");
      return;
    }

    $tour_queue = $this->queueFactory->get(TourFetcher::QUEUE_NAME);
    $city_queue = $this->queueFactory->get(CityFetcher::QUEUE_NAME);
    $country_queue = $this->queueFactory->get(CountryFetcher::QUEUE_NAME);

    $country_queue->createItem(CountryFetcher::createQueueItemData(0));

    $lang_codes = $this->iziLanguages->get_preferred_content_languages();
    foreach ($lang_codes as $lang_code) {
      $tour_queue->createItem(TourFetcher::createQueueItemData($lang_code, 0));
      $city_queue->createItem(CityFetcher::createQueueItemData($lang_code, 0));
    }
    $this->logger->notice("All IZI Alias Fetched Queues started.");
  }

  /**
   * Clear all alias data. DANGEROUS!
   *
   * @usage izi-clear
   *  Clears izi aliases and ALL redirects!
   * @command izi_urlalias:izi-clear
   * @aliases izi-clear
   */
  public function clearData() {
    $connection = \Drupal::database();

    $routes = [
      '/city',
      '/country',
      '/browse',
    ];

    foreach ($routes as $route) {
      $connection->delete('path_alias')
        ->condition('path', "$route%", 'LIKE')
        ->execute();
    }

    $connection->delete('xmlsitemap')
      ->condition('type', "izi_urlalias%", 'LIKE')
      ->execute();

    $connection->truncate('izi_urlalias_status')->execute();

  }

  /**
   * Trigger fetch & process on tour queue.
   *
   * @param string $language
   *   Fill queue with items matching language (optional).
   *
   * @usage izi_start_tours en
   *  Start fetching tours
   * @command izi_urlalias:start_tours
   * @aliases izi_start_tours
   */
  public function startTours($language) {
    $queue_name = TourFetcher::QUEUE_NAME;
    $queue = $this->queueFactory->get($queue_name);

    $lang_codes = empty($language)
      ? $this->iziLanguages->get_preferred_content_languages()
      : [$language];

    foreach ($lang_codes as $lang_code) {
      $queue->createItem(TourFetcher::createQueueItemData($lang_code, 0));
    }
  }

  /**
   * Trigger fetch & process on city queue.
   *
   * @param string $language
   *   Fill queue with items matching language (optional).
   *
   * @usage izi_start_cities en
   *  Start fetching cities
   * @command izi_urlalias:start_cities
   * @aliases izi_start_cities
   */
  public function startCities($language) {
    $queue_name = CityFetcher::QUEUE_NAME;
    $queue = $this->queueFactory->get($queue_name);

    $lang_codes = empty($language)
      ? $this->iziLanguages->get_preferred_content_languages()
      : [$language];

    foreach ($lang_codes as $lang_code) {
      $queue->createItem(CityFetcher::createQueueItemData($lang_code, 0));
    }
  }

  /**
   * Trigger fetch & process on country queue.
   *
   * @usage izi_start_countries
   *  Start fetching countries
   * @command izi_urlalias:start_countries
   * @aliases izi_start_countries
   */
  public function startCountries() {
    $queue_name = CountryFetcher::QUEUE_NAME;
    $queue = $this->queueFactory->get($queue_name);
    $queue->createItem(CountryFetcher::createQueueItemData(0));
  }

  /**
   *
   */
  protected function _queues_empty() {
    $queue_names = [
      CityAlias::QUEUE_NAME,
      CountryAlias::QUEUE_NAME,
      TourAlias::QUEUE_NAME,
      TourFetcher::QUEUE_NAME,
      CityFetcher::QUEUE_NAME,
      CountryFetcher::QUEUE_NAME,
    ];

    foreach ($queue_names as $queue_name) {

      $queue = $this->queueFactory->get($queue_name);
      if ($queue->numberOfItems() > 0) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /***************************************
   * Fetcher functions.
   ***************************************/
  protected function fetchCountries($offset) {
    // Load a number of objects.
    $izi_client = $this->iziLibizi->getLibiziClient();
    $lang_codes = $this->iziLanguages->get_preferred_content_languages();
    $request = $izi_client
      ->getCountries($lang_codes)
      ->setForm(MultipleFormInterface::FORM_COMPACT)
      ->setIncludes(['translations'])
      ->setOffset($offset)
      ->setLimit(self::IZI_URLALIAS_CHUNK);
    usleep(self::IZI_URLALIAS_SLEEP);
    $objects = $request->execute();
    $count = count($objects);
    return $count ? $objects : [];
  }

  /**
   *
   */
  protected function fetchCities($lang_code, $offset) {
    // Load a number of objects.
    $izi_client = $this->iziLibizi->getLibiziClient();
    $request = $izi_client
      ->getCities([$lang_code])
      ->setForm(MultipleFormInterface::FORM_COMPACT)
      ->setIncludes(['translations'])
      ->setOffset($offset)
      ->setLimit(self::IZI_URLALIAS_CHUNK);
    usleep(self::IZI_URLALIAS_SLEEP);
    $objects = $request->execute();
    $count = count($objects);
    return $count ? $objects : [];
  }

  /**
   *
   */
  protected function fetchTours($lang_code, $offset, $search_string = '') {
    // Load a number of objects.
    $izi_client = $this->iziLibizi->getLibiziClient();
    $request = $izi_client->search([$lang_code], $search_string)
      ->setTypes(['tour,museum,collection'])
      ->setIncludes(['children'])
      ->setForm(MultipleFormInterface::FORM_FULL)
      ->setLimit(self::IZI_URLALIAS_OBJ_CHUNK)
      ->setOffset($offset);
    $objects = $request->execute();
    usleep(self::IZI_URLALIAS_SLEEP);
    $count = count($objects);
    return $count ? $objects : [];
  }

  /**
   * Clear alias status to rebuild all aliases.
   *
   * @usage izi_urlalias:izi_reindex
   *  Clear alias status so all aliases will be reprocessed.
   *
   * @command izi_urlalias:reindex
   * @aliases izi_reindex
   */
  public function reindex() {
    $connection = \Drupal::database();
    $connection->truncate('izi_urlalias_status')->execute();
    $this->logger->notice('izi_urlalias_status truncated, all aliases will be reprocessed.');
  }

  /**
   * Debugging and testing commands. */

  /**
   * Fetch specific tour & add to alias queue.
   *
   * @usage izi_urlalias:gettour
   *  Add a specific tour to queue for processing.
   * @param lang
   *   Fill queue with items matching search string.
   * @param uuid
   *   UUID to fetch
   *
   * @command izi_urlalias:gettour
   * @aliases izi_gettour
   */
  public function fetchSingleTour($lang, $uuid) {
    izi_urlalias_clear_status_item($uuid, $lang);
    $queue_name = TourAlias::QUEUE_NAME;
    $queue = $this->queueFactory->get($queue_name);
    $izi_client = $this->iziLibizi->getLibiziClient();
    $request = $izi_client->getMtgObjectByUuid([$lang], $uuid);
    $object = $request->execute();

    if ($object) {
      $queue->createItem($object);
      $this->logger->notice(t('Fetcher: Saved @title @uuid to the queue.', [
        '@uuid' => $uuid,
        '@title' => $object->getTitle(),
        '@lang' => $lang,
      ]));
    }
  }

  /**
   * Fetch specific city & add to alias queue.
   *
   * @usage izi_urlalias:getcity
   *  Add a specific city to queue for processing.
   * @param lang
   *   Fill queue with items matching search string.
   * @param uuid
   *   UUID to fetch
   *
   * @command izi_urlalias:getcity
   * @aliases izi_getcity
   */
  public function fetchSingleCity($lang, $uuid): void {
    izi_urlalias_clear_status_item($uuid, $lang);
    $queue_name = CityAlias::QUEUE_NAME;
    $queue = $this->queueFactory->get($queue_name);

    $izi_client = $this->iziLibizi->getLibiziClient();
    $request = $izi_client->getCityByUuid([$lang], $uuid)
      ->setForm(MultipleFormInterface::FORM_COMPACT)
      ->setIncludes(['translations']);
    $object = $request->execute();

    if ($object) {
      $queue->createItem($object);
      $this->logger->notice(t('Fetcher: Saved @title @uuid to the queue.', [
        '@uuid' => $uuid,
        '@title' => $object->getTitle(),
        '@lang' => $lang,
      ]));
    }
  }

}
