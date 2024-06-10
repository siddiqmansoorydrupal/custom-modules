<?php

namespace Drupal\cde_order\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a custom block with a form containing two buttons.
 *
 * @Block(
 *   id = "custom_order_block",
 *   admin_label = @Translation("Custom Order Block"),
 *   category = @Translation("Custom")
 * )
 */
class CustomOrderBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];

    // Build the form.
    $build['form'] = \Drupal::formBuilder()->getForm('Drupal\cde_order\Form\CustomOrderForm');

    return $build;
  }

}
