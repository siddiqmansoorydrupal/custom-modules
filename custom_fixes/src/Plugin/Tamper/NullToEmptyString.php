<?php

namespace Drupal\custom_fixes\Plugin\Tamper;

use Drupal\tamper\TamperableItemInterface;
use Drupal\tamper\TamperBase;

/**
 * Plugin implementation for null to empty string.
 *
 * @Tamper(
 *   id = "null_to_empty",
 *   label = @Translation("Null to empty string"),
 *   description = @Translation("Convert null value to empty string."),
 *   category = "Text"
 * )
 */
class NullToEmptyString extends TamperBase {

  /**
   * {@inheritdoc}
   */
  public function tamper($data, TamperableItemInterface $item = NULL) {

    if($data === null)
    {
      $data = '';
    }
    return $data;
  }

}
