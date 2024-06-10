<?php

namespace Drupal\cde_commerce\EventSubscriber;

use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_shipping\Entity\Shipment;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Cart events subscriber.
 */
class CdeCartEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CdeCartEventSubscriber object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    MessengerInterface $messenger,
    AccountInterface $current_user,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CartEvents::CART_ORDER_ITEM_REMOVE => ['onCartOrderItemRemove', -100],
      CartEvents::CART_ENTITY_ADD => [['onCartOrderItemAdd', -100]]
    ];
  }

  /**
   * Shows a message when an order item has been removed from the cart.
   *
   * @param \Drupal\commerce_cart\Event\CartOrderItemRemoveEvent $event
   *   The cart event.
   */
  public function onCartOrderItemRemove(CartOrderItemRemoveEvent $event) {
    $this->messenger->addStatus('Product has been deleted from cart.');
  }

  /**
   * Add a related product automatically
   *
   * @param \Drupal\commerce_cart\Event\CartEntityAddEvent $event
   *   The cart add event.
   */
  public function onCartOrderItemAdd(CartEntityAddEvent $event) {
    $order = $event->getCart();

    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $order->get('shipments')->referencedEntities();
    if (!empty($shipments)) {
      return;
    }

    $first_shipment = Shipment::create([
      'shipping_service' => 'cde_ups_ground',
      'order_id' => $order->id(),
      'type' => $this->getShipmentType($order),
      'title' => $this->t('Shipment'),
      'state' => 'ready',
    ]);

    $shipping_method_storage = $this->entityTypeManager->getStorage('commerce_shipping_method');
    $shipping_methods = $shipping_method_storage->loadMultipleForShipment($first_shipment);
    $first_shipping_method = reset($shipping_methods);
    $first_shipment->setShippingMethod($first_shipping_method);

    // Set shipping profile.
    if ($this->currentUser->isAuthenticated()) {
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      if ($shipping_profile = $profile_storage->loadByUser($this->currentUser, 'shipping')) {
        $first_shipment->setShippingProfile($shipping_profile);
      }
    }

    $first_shipment->save();
    $order->set('shipments', $first_shipment);
  }

  /**
   * Gets the shipment type for the current order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return string
   *   The shipment type.
   */
  protected function getShipmentType(OrderInterface $order) {
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $order_type_storage->load($order->bundle());

    return $order_type->getThirdPartySetting('commerce_shipping', 'shipment_type');
  }

}
