<?php

namespace Drupal\izi_ads;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension.
 */
class IziAdsShowAdsTwigExtension extends AbstractExtension {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new IziAdsShowAdsTwigExtension object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('izi_ads_show', function () {
        return $this->showAds();
      }),
    ];
  }

  /**
   *
   */
  protected function showAds() {

    $entities = $this->entityTypeManager
      ->getStorage('izi_ads_exclusion')
      ->loadMultiple();

    $current_uri = \Drupal::request()->getRequestUri();

    /** @var \Drupal\izi_ads\Entity\IziAds $entity */
    foreach ($entities as $entity) {
      $path = $entity->getPath();
      if ($current_uri == $path) {
        $expires = strtotime($entity->getExpires());
        if (time() < $expires) {
          return FALSE;
        }
      }
    }
    return TRUE;
  }

}
