<?php

namespace Drupal\izi_ads;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of izi ads exclusions.
 */
class IziAdsExclusionListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['id'] = $this->t('Machine name');
    $header['path'] = $this->t('Path');
    $header['expires'] = $this->t('Expires');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\izi_ads\IziAdsExclusionInterface $entity */
    $row['id'] = $entity->id();
    $row['label'] = $entity->label();
    $row['path'] = $entity->getPath();
    $row['expires'] = $entity->getExpires();
    return $row + parent::buildRow($entity);
  }

}
