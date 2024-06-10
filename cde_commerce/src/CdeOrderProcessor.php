<?php

namespace Drupal\cde_commerce;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\User;

/**
 * Removes order items that contain out-of-stock products.
 */
class CdeOrderProcessor implements OrderProcessorInterface {

  /**
   * The messenger.
   */
  protected $messenger;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a CdeOrderProcessor object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(
    MessengerInterface $messenger,
    AccountInterface $current_user
  ) {
    $this->messenger = $messenger;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    $show_message = FALSE;

    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      $product = $purchased_entity->getProduct();

      if (!$product || $product->bundle() !== 'knebridge_product_nodes') {
        continue;
      }

      $field_in_stock = $product->get('field_in_stock')->value;
      $field_offline_item = $product->get('field_offline_item')->value;
      if ($field_in_stock !== 'Y' && !$field_offline_item) {
        $order->removeItem($order_item);
        $order_item->delete();
        $show_message = TRUE;
      }
      else {
        $user = User::load($this->currentUser->id());
        $user_5_box_price = !$user->get('field_user_5_box_price')->isEmpty() ? $user->get('field_user_5_box_price')->value : FALSE;
        if ($order_item->getQuantity() >= 5 || $user_5_box_price) {
          $field_5price = !$product->get('field_5price')->isEmpty() ? $product->get('field_5price')->first()->toPrice() : FALSE;
          if ($field_5price && !$field_5price->isZero()) {
            $order_item->setUnitPrice($field_5price, TRUE);
          }
        }
      }
    }

    if ($show_message) {
      $this->messenger->addStatus('Some products weren\'t copied to the cart as they aren\'t currently available. Contact us and let us know how can we help you.');
    }
  }

}
