<?php

namespace Drupal\izi_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a block.
 *
 * @Block(
 *   id = "izi_blocks_download",
 *   admin_label = @Translation("IZI Download"),
 *   category = @Translation("IZI Blocks")
 * )
 */
class IziDownloadBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    return [
      '#theme' => 'izi_blocks_download',
      '#data' => [],
    ];
  }

}
