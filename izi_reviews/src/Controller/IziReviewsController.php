<?php

namespace Drupal\izi_reviews\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\izi_reviews\HelpersService;
use Drupal\izi_reviews\ReviewsService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Returns responses for izi_reviews routes.
 */
class IziReviewsController extends ControllerBase {

  /**
   * Review service.
   *
   * @var \Drupal\izi_reviews\ReviewsService
   */
  protected ReviewsService $reviews_service;

  /**
   * Helpers service.
   *
   * @var \Drupal\izi_reviews\HelpersService
   */
  protected HelpersService $helpers_service;

  /**
   * ModalFormContactController constructor.
   *
   * @param \Drupal\izi_reviews\ReviewsService $reviews_service
   * @param \Drupal\izi_reviews\HelpersService $helpers_service
   */
  public function __construct(ReviewsService $reviews_service, HelpersService $helpers_service) {
    $this->reviews_service = $reviews_service;
    $this->helpers_service = $helpers_service;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('izi_reviews.reviews_service'),
      $container->get('izi_reviews.helpers_service'),
    );
  }

  /**
   * Reviews AJAX load more page callback.
   * Delivers JSON with info whether to show a load more, the next offset and
   * the rendered results.
   *
   * @param int $offset
   *   The number of items to skip when performing the search query.
   * @param string $uuid
   *   The uuid of the object to get the reviews from.
   *
   * @throws \Exception
   */
  public function izi_reviews_ajax_load_more(int $offset, string $uuid) {
    $return = [];

    $rating_object = $this->reviews_service->izi_reviews_load_rating_and_reviews_object($uuid, $offset, IZI_REVIEWS_SHOW_LIMIT);
    $count = $rating_object->getTotalCount();
    $reviews = $rating_object->getReviews();

    // Set the load more count.
    $return['load_more_count'] = $this->helpers_service->_izi_reviews_load_more_count($count, $offset);

    // Set the new offset for the load more button.
    $return['offset'] = $offset + IZI_REVIEWS_SHOW_LIMIT;

    // Create the loaded reviews HTML.
    $return['results'] = \Drupal::service('renderer')
      ->render($this->helpers_service->_izi_reviews_list_reviews_render($reviews));

    // Deliver JSON.
    return new JsonResponse($return);
  }

}
