<?php

namespace Drupal\cde_search_api\EventSubscriber;

use Drupal\Core\Cache\Cache;
use Drupal\facets\Event\FacetsEvents;
use Drupal\facets\Event\GetFacetCacheMaxAge;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FacetCacheMaxAge implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[FacetsEvents::GET_FACET_CACHE_MAX_AGE][] = ['onFacetLoad'];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function onFacetLoad(GetFacetCacheMaxAge $event) {
    if ($event->getCacheMaxAge() !== Cache::PERMANENT) {
      $event->setCacheMaxAge(Cache::PERMANENT);
    }
  }

}
