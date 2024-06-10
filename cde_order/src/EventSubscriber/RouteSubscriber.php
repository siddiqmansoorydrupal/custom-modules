<?php

namespace Drupal\cde_order\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Check if the route you want to alter exists.
    if ($route = $collection->get('entity.commerce_order.resend_receipt_form')) {
      // Set the custom controller for the existing route.
      $route->setDefault('_controller', '\Drupal\cde_order\Controller\RouteController::orderEmail');
    }
  }
}
