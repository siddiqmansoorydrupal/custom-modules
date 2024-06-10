<?php

namespace Drupal\izi_reviews\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 *
 */
class IziReviewsRemoveReviewForm implements CommandInterface {

  /**
   *
   */
  public function __construct() {}

  /**
   *
   */
  public function render() {
    return [
      'command' => 'iziReviewsRemoveReviewForm',
      'method' => NULL,
    ];
  }

}
