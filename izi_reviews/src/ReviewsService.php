<?php

namespace Drupal\izi_reviews;

use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\izi_libizi\Libizi;
use Drupal\izi_reviews\Form\ReviewForm;
use Triquanta\IziTravel\Client\Client;
use Triquanta\IziTravel\DataType\RatingReviews;

/**
 *
 */
class ReviewsService {

  use LoggerChannelTrait;

  /**
   * The izi_libizi.libizi service.
   *
   * @var \Drupal\izi_libizi\Libizi
   */
  protected Libizi $libizi;

  /**
   * @var \Triquanta\IziTravel\Client\Client
   */
  protected Client $izi_client;

  /**
   * @param \Drupal\izi_libizi\Libizi $libizi
   *   The izi_libizi.libizi service.
   * @param \Drupal\izi_reviews\HelpersService $helpers_service
   */
  public function __construct(Libizi $libizi, HelpersService $helpers_service) {
    $this->libizi = $libizi;
    $this->helpers_service = $helpers_service;
  }

  /**
   * Prepare reviews block render array.
   *
   * @return mixed[]
   **/
  public function izi_reviews_form_and_listing_render_block_content($uuid) {
    $block = [
      '#theme' => 'izi_reviews_form_and_listing_block',
      '#uuid' => $uuid,
      '#offset' => IZI_REVIEWS_SHOW_LIMIT,
    ];

    // Add JS (before ajax.js to be able to react to submits first).
    $block['#attached']['library'][] = "izi_reviews/izi_reviews.form_and_listing_block";

    $rating_object = $this->izi_reviews_load_rating_and_reviews_object($uuid, 0, IZI_REVIEWS_SHOW_LIMIT);

    if (!empty($rating_object)) {
      $count = $rating_object->getTotalCount();
      $reviews = $rating_object->getReviews();
      // Create render array of reviews.
      $block['#reviews'] = $this->helpers_service->_izi_reviews_list_reviews_render($reviews);
    }
    else {
      $count = 0;
      $block['#reviews'][] = [
        '#markup' => '<li>' . t('Reviews could not be loaded at this time, please try again later.') . '</li>',
      ];
    }

    // Set total amount of reviews.
    $block['#count'] = $count;

    // How many reviews to load more.
    $block['#load_more_count'] = $this->helpers_service->_izi_reviews_load_more_count($count);

    $block['#number_of_reviews_text'] = [
      '#markup' => \Drupal::translation()
        ->formatPlural(
          $count,
          '<span class="count">@count</span> review',
          '<span class="count">@count</span> reviews'
      ),
    ];

    $block['#form'] = \Drupal::formBuilder()->getForm(ReviewForm::class, $uuid);

    return $block;
  }

  /**
   * Load rating and reviews object.
   *
   * @param string $uuid
   * @param int $limit
   * @param int $offset
   *
   * @return ?\Triquanta\IziTravel\DataType\RatingReviews
   *
   * @throws \Exception
   */
  public function izi_reviews_load_rating_and_reviews_object(string $uuid, int $offset = 0, int $limit = 5): ?RatingReviews {
    if (empty($uuid)) {

      $this->getLogger('izi_apicontent')
        ->warning('Empty UUID detected for review.');
      return NULL;
    }
    // Add static caching for performance.
    $objects = &drupal_static(__FUNCTION__);
    $static_cache_key = crc32($uuid . $limit . $offset);
    if (!isset($objects[$static_cache_key])) {
      try {
        // The reviews should be returned in all languages.
        // We do that by passing an empty string as language.
        /** @var \Triquanta\IziTravel\Request\Reviews $request */
        $request = $this->libizi->getLibiziClient()->getReviewsByUuid('', $uuid);

        if ($request) {
          $objects[$static_cache_key] = $request
            ->setLimit($limit)
            ->setOffset($offset)
            ->execute();
        }

        return $objects[$static_cache_key];
      }
      catch (\Exception $e) {
        watchdog_exception('izi_reviews', $e);
        return NULL;
      }
    }
    else {
      return $objects[$static_cache_key];
    }
  }

}
