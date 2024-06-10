<?php
namespace Drupal\izi_credit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Endroid\QrCode\QrCode;
use Symfony\Component\HttpFoundation\Response;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;

class CouponController extends ControllerBase {

  protected $entityTypeManager;
  protected $messenger;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->messenger = $messenger;
  }

  public function applyCoupon() {
    if (\Drupal::currentUser()->isAnonymous()) {
        // If not logged in, redirect to the login page with a destination parameter.
        $current_url = \Drupal::request()->getRequestUri();
        $destination = Url::fromUri('internal:' . $current_url)->toString();
        $url = Url::fromRoute('user.login', [], ['query' => ['destination' => $destination]]);
        return new RedirectResponse($url->toString());
    }  
    return $this->formBuilder()->getForm('Drupal\izi_credit\Form\CouponForm');
  }

  public function addToCoupons(Request $request) {
    // Check if the user is logged in.
    if (\Drupal::currentUser()->isAnonymous()) {
      // If not logged in, redirect to the login page with a destination parameter.
      $current_url = \Drupal::request()->getRequestUri();
      $destination = Url::fromUri('internal:' . $current_url)->toString();
      $url = Url::fromRoute('user.login', [], ['query' => ['destination' => $destination]]);
      return new RedirectResponse($url->toString());
    }

    try {
      // Retrieve query parameters.
      $query_params = \Drupal::request()->query;
      $sku = $query_params->get('sku');
      $currency = $query_params->get('currency');
      $price = $query_params->get('price');
      $title = $query_params->get('title');
      $return = $query_params->get('return');

      // Check if the product already exists.
      $product_storage = $this->entityTypeManager->getStorage('commerce_product');
      $products = $product_storage->loadByProperties(['type' => 'default', 'title' => $title]);

      if (empty($products)) {
        // Create a custom product if not found.
        $product = Product::create([
          'type' => 'default',
          'title' => $title,
          'status' => 1,
        ]);
        $product->save();
      } else {
        $product = reset($products);
      }

      $variation = $this->entityTypeManager->getStorage('commerce_product_variation')->loadByProperties(['sku' => $sku]);

      if (!$variation) {
        // Create the price object.
        $price_obj = new \Drupal\commerce_price\Price($price, $currency);

        // Create the product variation.
        $variation = ProductVariation::create([
          'type' => 'default',
          'sku' => $sku,
          'title' => $title,
          'status' => 1,
          'price' => $price_obj,
          'attribute_values' => [],
          'product_id' => $product->id(),
        ]);

        // Set the field_tour_link field.
        $variation->set('field_tour_link', ['title' => $title, 'uri' => $return]);
        $variation->set('field_tour_url', $return);

        // Save the variation and product.
        $variation->save();
        $variation = $this->entityTypeManager->getStorage('commerce_product_variation')->loadByProperties(['sku' => $sku]);
      }
      $current_user = \Drupal::currentUser();

      // Check if any variations were found.
      if (!empty($variation)) {
        $variation = reset($variation);
        $variation_id = $variation->id();
      }

      $is_added = $this->checkIfVariationAddedByCurrentUser($current_user, $variation_id);

      if (!$is_added) {
        $reseller_uid = \Drupal::currentUser()->id();
        $coupon_code = "";
        $created = \Drupal::time()->getRequestTime();

        $connection = \Drupal::database();
        $connection->insert('izi_credit')
          ->fields([
            'product_id' => $variation_id,
            'reseller_uid' => $reseller_uid,
            'coupon_code' => $coupon_code,
            'created' => $created,
            'expiry_date' => $created,
            'tour_price' => 0,
          ])
          ->execute();

        // Generate QR code
        /*$qrCode = new QrCode($coupon_code);
        $qrCode->writeFile('sites/default/files/qr_codes/' . $coupon_code . '.png');*/

        \Drupal::messenger()->addStatus($this->t('Coupon created for @title with code: @code', ['@title' => $title, '@code' => $coupon_code]));
      }

    } catch (\Exception $e) {
      // Handle exceptions.
      $this->messenger->addError($this->t('An error occurred: @message', ['@message' => $e->getMessage()]));
    }

    // Redirect to the cart page in case of error.
    return $this->redirect('izi_credit.form');
  }

  /**
   * Checks if the variation has been added by the current user.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param int $variation_id
   *   The ID of the variation to check.
   *
   * @return bool
   *   TRUE if the variation is already added by the current user, FALSE otherwise.
   */
  public function checkIfVariationAddedByCurrentUser($current_user, $variation_id) {
    $connection = \Drupal::database();
    $query = $connection->select('izi_credit', 'ic')
      ->fields('ic', ['product_id'])
      ->condition('ic.reseller_uid', $current_user->id())
      ->condition('ic.product_id', $variation_id)
      ->execute()
      ->fetchField();

    return !empty($query);
  }
}
