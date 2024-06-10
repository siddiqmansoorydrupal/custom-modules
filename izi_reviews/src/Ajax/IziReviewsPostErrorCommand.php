<?php

namespace Drupal\izi_reviews\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 *
 */
class IziReviewsPostErrorCommand implements CommandInterface {

  private string $messages;

  /**
   *
   */
  public function __construct($messages) {
    $this->messages = $messages;
  }

  /**
   *
   */
  public function render() {
    return [
      'command' => 'iziReviewsPostError',
      'method' => NULL,
      'messages' => $this->messages,
    ];
  }

}
