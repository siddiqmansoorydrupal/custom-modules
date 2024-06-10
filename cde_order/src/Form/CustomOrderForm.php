<?php

namespace Drupal\cde_order\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_price\Price;

/**
 * Custom form containing two buttons.
 */
class CustomOrderForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'custom_order_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Button 1.
    $form['apply_pricing_rules'] = [
      '#type' => 'submit',
      '#value' => t('Apply pricing rules'),
      '#submit' => ['::applyPricingRules'],
    ];

    // Button 2.
    $form['simulate_checkout_completion'] = [
      '#type' => 'submit',
      '#value' => t('Simulate checkout completion'),
      '#submit' => ['::simulateCheckoutCompletion'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle form submission if needed.
  }

  /**
   * Submit handler for Button 1.
   */
  public function applyPricingRules(array &$form, FormStateInterface $form_state) {
    // Action for Button 1 (Apply pricing rules).
    // Example action: Redirect to a specific URL.
    //$form_state->setRedirect('entity.node.canonical', ['node' => 1]);

    $current_path = \Drupal::service('path.current')->getPath();
    $order_id = NULL;
    $params = [];

    if (preg_match('/\/admin\/commerce\/orders\/(\d+)\/edit/', $current_path, $matches)) {
      $order_id = $matches[1];
    }

    $cart_order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $cart_order = $cart_order_storage->load($order_id);
    $customer = $cart_order->getCustomer();

    $user = \Drupal\user\Entity\User::load($customer->id());
    $field_values = $user->field_user_5_box_price->value;

    foreach ($cart_order->getItems() as $order_item) {
      $product_variation = $order_item->getPurchasedEntity();
      $product_id = $product_variation->get('product_id')->getValue()[0]['target_id'];
      $product = Product::load($product_id);

      if ($product && $product->get('field_in_stock')->getString() !== 'Y' && $product->get('field_offline_item')->getString() !== '1') {
        $order_item->delete();
        $deleted = true;
         \Drupal::messenger()->addStatus("Some products weren't copied to the cart as they aren't currently available. Contact us and let us know how can we help you.");
      }
      else {
        if ($order_item->getQuantity() >= 5 || $field_values == 1) {
          $field_5price = $product->get('field_5price')->getValue()[0]['number'];
          if ($field_5price && $field_5price > 0) {
            $price_currency = $product->get('field_5price')->getValue()[0]['currency_code'];
            $getPrice = new Price($field_5price, $price_currency);
          }
          else {
            $getPrice = $product_variation->getPrice();
          }
        }
        else {
          $getPrice = $product_variation->getPrice();
        }
        $order_item->setUnitPrice($getPrice, TRUE);
        $order_item->save();
      }
    }

    $cart_order->set('field_collect_number', strtoupper($shipping_collect_number));
    $cart_order->save();
  }

  /**
   * Submit handler for Button 2.
   */
  public function simulateCheckoutCompletion(array &$form, FormStateInterface $form_state) {
    $current_path = \Drupal::service('path.current')->getPath();
    $order_id = NULL;
    $params = [];

    if (preg_match('/\/admin\/commerce\/orders\/(\d+)\/edit/', $current_path, $matches)) {
      $order_id = $matches[1];
    }

    // Check if the order ID was extracted successfully.
    if (!is_null($order_id)) {
      $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->load($order_id);
      $mailManager = \Drupal::service('plugin.manager.mail');
      $token_service = \Drupal::token();
      // Check if the order entity exists.
      if ($order) {
        $net_30_status = $order->get('field_net_30_status')->getString();
        if ($net_30_status != 'net30_open' && $net_30_status != 'net30_paid') {
          $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');
          // Load all commerce email entities.
          $emails = $email_storage->loadMultiple();
          // Iterate through the loaded entities.
          foreach ($emails as $email) {
            // Output or process each commerce email entity as needed.
            $email_id = $email->id();
            $email_label = $email->label();
            if ($email_id=='on_order_complete_to_admin') {
              $email_body = $email->get('body')['value'];
              $site_mail = \Drupal::config('system.site')->get('mail');
              $to = $site_mail;
              $token_data = [
                'commerce_order' => $order,
              ];
              $token_options = ['clear' => TRUE];
              $params['message'] = $token_service->replace($email_body, $token_data, $token_options);
              $params['order_id'] = $order->id();
              $langcode = \Drupal::currentUser()->getPreferredLangcode();
              $module = 'cde_order';
              $key = $email_id;
              $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL);
              if ($result['result'] !== TRUE) {
                \Drupal::messenger()->addMessage('There was a problem sending your message to Site Admin and it was not sent.');
              }
              else {
                \Drupal::messenger()->addMessage('Order email has been sent to Site Admin.');
              }
            }

            if ($email_id == 'on_order_complete_to_user') {
              $email_body = $email->get('body')['value'];
              $token_data = [
                'commerce_order' => $order,
              ];
              $token_options = ['clear' => TRUE];
              $params['message'] = $token_service->replace($email_body, $token_data, $token_options);
              $params['order_id'] = $order->id();
              $langcode = \Drupal::currentUser()->getPreferredLangcode();
              $module = 'cde_order';
              $key = $email_id;
              $to = $order->getEmail();
              if ($order->hasField('field_cc_email')) {
                $email_cc = $order->get('field_cc_email')->getString();
                if (!empty($email_cc) && $to != $email_cc) {
                  $site_mail = \Drupal::config('system.site')->get('mail');
                  $params['headers']['cc'] = str_replace($site_mail,'', $email_cc);
                }
              }
              $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL);
              if ($result['result'] !== TRUE) {
                \Drupal::messenger()->addMessage('There was a problem sending your message to Customer and it was not sent.');
              }
              else {
               // \Drupal::messenger()->addMessage('Order email has been sent to Customer.');
              }
            }
          }
        }
      }
      else {
        \Drupal::messenger()->addError('Failed to load order with ID: ' . $order_id);
      }
    }
    else {
      \Drupal::messenger()->addError('Failed to extract order ID from the URL.');
    }

    $form_state->set('confirmation_required', TRUE);
    $form_state->setRebuild(TRUE);
    \Drupal::messenger()->addMessage($this->t('Checkout completion rules have been executed for the order.'));
  }

}
