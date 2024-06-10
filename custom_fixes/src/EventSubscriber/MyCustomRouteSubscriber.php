<?php

namespace Drupal\custom_fixes\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;
 
    /**
     * MyCustom Route subscriber.
     */
    class MyCustomRouteSubscriber extends RouteSubscriberBase {

      /**
       * {@inheritdoc}
       */
      public static function getSubscribedEvents(): array {
        $events = parent::getSubscribedEvents();
        $events[RoutingEvents::ALTER] = ['onAlterRoutes', -300];
        return $events;
      }

      /**
       * {@inheritdoc}
       */
      protected function alterRoutes(RouteCollection $collection) {
        if ($route = $collection->get('user.logout')) {
          $route->setDefaults([
            '_controller' => '\Drupal\custom_fixes\Controller\MyCustomUserLogoutController::logout',
          ]);
        }
      }

    }