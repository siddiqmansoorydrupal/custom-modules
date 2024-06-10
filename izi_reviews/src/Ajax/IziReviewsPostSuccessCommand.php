<?php

namespace Drupal\izi_reviews\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 *
 */
class IziReviewsPostSuccessCommand implements CommandInterface {

  private string $new_review;

  /**
   *
   */
  public function __construct($new_review) {
    $this->new_review = $new_review;
  }

  /**
   *
   */
  public function render() {
    return [
      'command' => 'iziReviewsPostSuccess',
      'method' => NULL,
      'new_review' => htmlspecialchars($this->new_review),
    ];
  }

}
