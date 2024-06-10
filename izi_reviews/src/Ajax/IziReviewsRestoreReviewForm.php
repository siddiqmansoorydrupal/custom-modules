<?php

namespace Drupal\izi_reviews\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 *
 */
class IziReviewsRestoreReviewForm implements CommandInterface {

  /**
   *
   */
  public function __construct() {}

  /**
   *
   */
  public function render() {
    return [
      'command' => 'iziReviewsRestoreReviewForm',
      'method' => NULL,
    ];
  }

}
