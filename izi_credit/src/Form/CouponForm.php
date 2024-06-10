<?php

namespace Drupal\izi_credit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;

class CouponForm extends FormBase {

  protected $cartProvider;
  protected $entityTypeManager;
  protected $messenger;

  public function __construct(CartProviderInterface $cartProvider, EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger) {
    $this->cartProvider = $cartProvider;
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  public function getFormId() {
    return 'izi_credit_coupon_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['coupon_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Coupon Code'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply Coupon'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $coupon_code = $form_state->getValue('coupon_code');
    $connection = Database::getConnection();
    $query = $connection->select('izi_credit', 'ic')
      ->fields('ic', ['product_id', 'used', 'expiry_date'])
      ->condition('coupon_code', $coupon_code);

    $rows = $query->execute()->fetchAll();

    if (empty($rows)) {
      $this->messenger->addError($this->t('Invalid coupon code.'));
      return;
    }

    $valid = true;

    foreach ($rows as $row) {
      if ($row->used == 1) {
        $this->messenger->addError($this->t('This coupon code has been already redeemed.'));
        $valid = false;
        break;
      }

      if ($row->expiry_date < time()) {
        $this->messenger->addError($this->t('This coupon code has expired.'));
        $valid = false;
        break;
      }
    }

    if ($valid) {
      // Check if the promotion already exists.
      $promotion_exists = $this->entityTypeManager
        ->getStorage('commerce_promotion')
        ->getQuery()
        ->accessCheck(FALSE) // Disable access checking
        ->condition('name', $coupon_code)
        ->count()
        ->execute();

      if (!$promotion_exists) {
        $product_variation_ids = [];
        foreach ($rows as $row) {
          $product_variation = ProductVariation::load($row->product_id);
          if ($product_variation) {
            $product_variation_ids[] = $product_variation->uuid();
          }
        }

        $promotion = Promotion::create([
          'name' => $coupon_code,
          'display_name' => $coupon_code,
          'order_types' => ['default'],
          'stores' => [1], // Assuming store ID is 1
          'offer' => [
            [
              'target_plugin_id' => 'order_item_percentage_off',
              'target_plugin_configuration' => [
                'percentage' => '1.0', // 100% off as an example
                'display_inclusive' => TRUE,
                'conditions' => [
                  [
                    'plugin' => 'order_item_purchased_entity:commerce_product_variation',
                    'configuration' => [
                      'entities' => $product_variation_ids,
                    ],
                  ],
                ],
              ],
            ],
          ],
          'status' => TRUE,
          'usage_limit' => 1, // Limit total usage to 1
          'usage_limit_customer' => 1, // Limit usage per customer to 1
        ]);
        $promotion->save();
      }

      // Check if a coupon with the same code already exists.
      $coupon_exists = $this->entityTypeManager
        ->getStorage('commerce_promotion_coupon')
        ->getQuery()
        ->accessCheck(FALSE) // Disable access checking
        ->condition('code', $coupon_code)
        ->count()
        ->execute();

      if (!$coupon_exists) {
        // If coupon doesn't exist, create a new one.
        $promotion = $this->entityTypeManager->getStorage('commerce_promotion')->loadByProperties(['name' => $coupon_code]);
        $promotion = reset($promotion);

        $coupon = Coupon::create([
          'code' => $coupon_code,
          'status' => TRUE,
          'promotion_id' => $promotion->id(),
          'usage_limit' => 1, // Limit usage to 1
          'customer_user_ids' => [$this->currentUser()->id()], // Restrict to the current user
        ]);
        $coupon->save();
      }

      $coupon = $this->entityTypeManager->getStorage('commerce_promotion_coupon')->loadByProperties(['code' => $coupon_code]);
      $coupon = reset($coupon);
      if ($coupon) {
        $user = $this->currentUser();
        $store_id = 1; // Assuming you have the store ID stored here or derive it in some other way
        $store = $this->entityTypeManager->getStorage('commerce_store')->load($store_id);
        $cart = $this->cartProvider->getCart('default', $store, $user);

        if (!$cart) {
          $cart = $this->cartProvider->createCart('default', $store, $user);
        } else {
          // Remove all items from the cart
          foreach ($cart->getItems() as $order_item) {
            $cart->removeItem($order_item, TRUE);
          }
          // Remove any previously applied coupons
          $cart->get('coupons')->setValue([]);
        }

        foreach ($rows as $row) {
          $product_variation = ProductVariation::load($row->product_id);
          if ($product_variation) {
            $order_item = OrderItem::create([
              'type' => 'default',
              'purchased_entity' => $product_variation,
              'quantity' => 1,
              'unit_price' => $product_variation->getPrice(), // 100% discount
            ]);
            $order_item->save();
            $cart->addItem($order_item);
          }
        }

        $cart->get('coupons')->appendItem($coupon->id());
        $cart->save();
		
		 // Update the used and used_uid fields in the database
        $current_user_id = $user->id();
        $connection->update('izi_credit')
          ->fields(['used' => 1, 'used_uid' => $current_user_id])
          ->condition('coupon_code', $coupon_code)
          ->execute();

		

        $this->messenger->addStatus($this->t('The coupon code has been successfully applied.'));
        $form_state->setRedirect('commerce_checkout.form', ['commerce_order' => $cart->id()]);
      }
    }
  }
}
