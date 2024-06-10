<?php

namespace Drupal\izi_urlalias\Plugin\QueueWorker;

/**
 * Defines 'izi_urlalias_city_fetcher' queue worker.
 *
 * @QueueWorker(
 *   id = "izi_urlalias_city_fetcher",
 *   title = @Translation("City Fetcher Worker"),
 *   cron = {"time" = 60}
 * )
 */
class CityFetcher extends IziQueueWorkerBase {

  public const QUEUE_NAME = 'izi_urlalias_city_fetcher';

  public const FETCH_CHUNK_SIZE = 50;

  /**
   *
   */
  public static function createQueueItemData($language, $offset) {
    return [
      'id' => time(),
      'created' => date('c'),
      'offset' => $offset,
      'language' => $language,
    ];
  }

  /**
   *
   */
  public function processItem($data) {

    $queue = $this->queueFactory->get(self::QUEUE_NAME);

    $offset = $data['offset'];
    $language = $data['language'];
    $limit = self::FETCH_CHUNK_SIZE;

    /** @var \Drupal\izi_urlalias\Fetcher $fetcher */
    $fetcher = \Drupal::getContainer()->get('izi_urlalias.fetcher');
    $count = $fetcher->getCities($language, $offset, $limit);
    if ($count === $limit) {
      $this->logger->notice($this->t('Fetched %c of %l citys in %lang. Creating new fetch job.', [
        '%c' => $count,
        '%l' => $limit,
        '%lang' => $language,
      ]));
      $queue->createItem($this->createQueueItemData($language, $offset + $count));
    }
    else {
      $this->logger->notice('Fetched %c of %l citys in %lang. Finished job que.', [
        '%c' => $count,
        '%l' => $limit,
        '%lang' => $language,
      ]);
    }

  }

}
