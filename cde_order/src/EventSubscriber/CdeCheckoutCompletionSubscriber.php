<?php

namespace Drupal\cde_order\EventSubscriber;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CdeCheckoutCompletionSubscriber implements EventSubscriberInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * Constructs a new CdeCheckoutCompletionSubscriber object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   */
  public function __construct(CartProviderInterface $cart_provider) {
    $this->cartProvider = $cart_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CheckoutEvents::COMPLETION => 'finalizeCart',
    ];
  }

  /**
   * Finalizes the cart upon checkout completion before the order is assigned
   * to retain the completed cart order ID in an anonymous user's session.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function finalizeCart(OrderEvent $event) {
    $order = $event->getOrder();
    if (!empty($order->cart->value)) {
      $this->cartProvider->finalizeCart($order, FALSE);
    }
  }

}
