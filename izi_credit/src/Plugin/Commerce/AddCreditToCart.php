<?php

namespace Drupal\izi_credit\Plugin\Commerce;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_order\Plugin\Commerce\OrderType;

/**
 * Plugin implementation for adding credits to a Commerce cart.
 */
class AddCreditToCart {

  public function addCredits(OrderInterface $order, $amount) {
    // Add credits to the user's balance after the order is completed.
    $user = $order->getCustomer();
    // Logic to update user's credit balance
  }

}
