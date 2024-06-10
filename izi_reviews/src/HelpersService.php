<?php

namespace Drupal\izi_reviews;

use Drupal\Component\Utility\Xss;

/**
 *
 */
class HelpersService {

  /**
   * Helper function to get the amount of stars from a rating.
   *
   * @param int $rating
   *
   * @return int
   */
  public function _izi_reviews_calculate_starts($rating) {
    $modifier = IZI_REVIEWS_RATING_MAX / IZI_REVIEWS_STARS_MAX;
    $stars = (int) round($rating / $modifier);
    return $stars;
  }

  /**
   * Helper function to create a render array of reviews.
   *
   * @param \Triquanta\IziTravel\DataType\Review[] $reviews
   *
   * @return mixed[]
   */
  public function _izi_reviews_list_reviews_render($reviews): array {
    $output = [];
    foreach ($reviews as $review) {
      // Name and text are already stripped of tags.
      $output[] = [
        '#theme' => 'izi_reviews_review',
        '#stars' => $this->_izi_reviews_calculate_starts($review->getRating()),
        '#name' => $review->getReviewName(),
        '#text' => $review->getReviewText(),
        // @todo (legacy) International date formats.
        '#date' => \Drupal::service('date.formatter')
          ->format(strtotime($review->getReviewDate()), 'post_date', 'm-d-Y'),
      ];
    }
    return $output;
  }

  /**
   * Helper function to get the rating from the amount of stars.
   *
   * @param int $stars
   *
   * @return int
   */
  public function _izi_reviews_calculate_rating($stars) {
    $modifier = IZI_REVIEWS_RATING_MAX / IZI_REVIEWS_STARS_MAX;
    $rating = (int) round($stars * $modifier);
    return $rating;
  }

  /**
   * Helper function to sanitize a string.
   * For now only strip tags using filter_xss.
   * Links and email may need to be stripped in the future.
   *
   * @param string $string
   *
   * @return string
   */
  public function _izi_reviews_sanitize_string($string) {
    return Xss::filter($string);
  }

  /**
   * Helper function to get the possible amount to load more with a max of the show limit.
   *
   * @param $count
   * @param int $offset
   *
   * @return int
   */
  public function _izi_reviews_load_more_count($count, $offset = 0) {
    // How many reviews are not shown?
    $not_shown_count = $count - IZI_REVIEWS_SHOW_LIMIT - $offset;
    // Maximum load more count is the show limit.
    $load_more_count = $not_shown_count > 0 ? $not_shown_count > IZI_REVIEWS_SHOW_LIMIT ? IZI_REVIEWS_SHOW_LIMIT : $not_shown_count : 0;
    return $load_more_count;
  }

}
