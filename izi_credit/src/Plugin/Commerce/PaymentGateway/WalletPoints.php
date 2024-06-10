<?php

namespace Drupal\izi_credit\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayBase;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Provides the Wallet Points payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "wallet_points",
 *   label = "Wallet Points",
 *   display_label = "Wallet Points",
 *   forms = {
 *     "add-payment" = "Drupal\commerce_payment\PluginForm\PaymentAddForm",
 *   },
 * )
 */
class WalletPoints extends PaymentGatewayBase {

  public function createPayment(PaymentInterface $payment, array $payment_details) {
    $order = $payment->getOrder();
    $user = $order->getCustomer();

    // Get user wallet points
    $wallet_points = $user->get('field_credits')->value;

    // Convert wallet points to amount
    $amount = $wallet_points * 0.1; // Assuming 10 credits = 1 USD

    // Check if user has enough points to cover the order total
    if ($amount < $order->getTotalPrice()->getNumber()) {
      throw new \InvalidArgumentException('Not enough wallet points to cover the order total.');
    }

    // Deduct points from user
    $new_wallet_points = $wallet_points - ($order->getTotalPrice()->getNumber() * 10);
    $user->set('field_credits', $new_wallet_points);
    $user->save();

    // Set payment status to completed
    $payment->setState('completed');
    $payment->setAmount($order->getTotalPrice());
    $payment->setRemoteId('wallet_points_' . $user->id());
    $payment->save();
  }

}
