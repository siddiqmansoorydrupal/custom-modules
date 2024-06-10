<?php

namespace Drupal\izi_urlalias\Plugin\QueueWorker;

/**
 * Defines 'izi_urlalias_country_fetcher' queue worker.
 *
 * @QueueWorker(
 *   id = "izi_urlalias_country_fetcher",
 *   title = @Translation("Country Fetcher Worker"),
 *   cron = {"time" = 60}
 * )
 */
class CountryFetcher extends IziQueueWorkerBase {

  public const QUEUE_NAME = 'izi_urlalias_country_fetcher';

  public const FETCH_CHUNK_SIZE = 50;

  /**
   *
   */
  public static function createQueueItemData($offset) {
    return [
      'id' => time(),
      'created' => date('c'),
      'offset' => $offset,
    ];
  }

  /**
   *
   */
  public function processItem($data) {

    $queue = $this->queueFactory->get(self::QUEUE_NAME);

    $offset = $data['offset'] ?? 0;
    $limit = self::FETCH_CHUNK_SIZE;

    /** @var \Drupal\izi_urlalias\Fetcher $fetcher */
    $fetcher = \Drupal::getContainer()->get('izi_urlalias.fetcher');
    $count = $fetcher->getCountries($offset, $limit);
    if ($count === self::FETCH_CHUNK_SIZE) {
      $this->logger->notice($this->t('Fetched %c of %l countries. Creating new fetch job.', [
        '%c' => $count,
        '%l' => $limit,
      ]));
      $queue->createItem($this->createQueueItemData($offset + $count));
    }
    else {
      $this->logger->notice($this->t('Fetched %c of %l countries. Finished job que.', [
        '%c' => $count,
        '%l' => $limit,
      ]));
    }

  }

}
