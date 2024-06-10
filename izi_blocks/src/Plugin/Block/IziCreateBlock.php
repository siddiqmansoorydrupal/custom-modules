<?php

namespace Drupal\izi_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block.
 *
 * @Block(
 *   id = "izi_blocks_create",
 *   admin_label = @Translation("Izi: Create Block"),
 *   category = @Translation("IZI Blocks")
 * )
 */
class IziCreateBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'izi_blocks_create',
      '#data' => [],
    ];
  }

}
