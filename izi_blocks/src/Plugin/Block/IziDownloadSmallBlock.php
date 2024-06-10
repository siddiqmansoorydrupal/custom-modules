<?php

namespace Drupal\izi_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block.
 *
 * @Block(
 *   id = "izi_blocks_download_small",
 *   admin_label = @Translation("IZI: Download Small"),
 *   category = @Translation("IZI Blocks")
 * )
 */
class IziDownloadSmallBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      '#theme' => 'izi_blocks_download_small',
      '#data' => [],
    ];
  }

}
