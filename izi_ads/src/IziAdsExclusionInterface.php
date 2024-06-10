<?php

namespace Drupal\izi_ads;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an izi ads exclusion entity type.
 */
interface IziAdsExclusionInterface extends ConfigEntityInterface {

  /**
   *
   */
  public function getPath();

  /**
   *
   */
  public function getExpires();

}
