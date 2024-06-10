<?php

namespace Drupal\stripe_pay\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use Drupal\commerce_price\Price;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\Order;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_payment\Entity\Payment;

use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_order\Resolver\DefaultCurrencyResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines StripeController class.
 */
class StripeController extends ControllerBase
{
    /**
     * Callback URL handling for Sendinblue API API.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   Request.
     *
     * @return array
     *   Return markup for the page.
     */
    public function payment_init(Request $request)
    {

        $payload = json_decode($request->getContent(), true);
        $productPrice = $payload['amount'] ?? '1';
        $productName = $payload['title'] ?? 'Product without name';
        $url = $payload['url'] ?? '';
        $productID = $payload['id'] ?? '0';
        $quantity = $payload['quantity'] ?? '1';

        if($productPrice < 1) {
            $productPrice = 1;
        }
		$productPrice = $productPrice*100;

        // Invoke the custom hook for success redirect.
        \Drupal::moduleHandler()->invokeAll('stripe_pay_success_redirect', [&$success_redirect_url]);

        if($url == '') {
            $url = Url::fromRoute('<front>', [], ['absolute' => true])->toString();
        }

        $success_redirect_url = Url::fromRoute('stripe_pay.payment_success', [], ['absolute' => true])->toString();
        $cancel_redirect_url = Url::fromRoute('stripe_pay.payment_cancel', [], ['absolute' => true])->toString();


        $stripe_pay_config = \Drupal::config('stripe_pay.settings');

        $currency_code = $stripe_pay_config->get('currency_code');
        $test_mode = $stripe_pay_config->get('test_mode');
        $secret_key = '';
        if($test_mode) {
            $secret_key = $stripe_pay_config->get('secret_key_test');
        } else {
            $secret_key = $stripe_pay_config->get('secret_key_live');
        }

        // Set API key
        $stripe = new \Stripe\StripeClient($secret_key);

        $response_data = array(
            'status' => 0,
            'error' => array(
                'message' => 'Invalid Request!'
            )
        );

        $currency = $stripe_pay_config->get('currency_code') ?? 'USD';

        $stripeAmount = round($productPrice, 2);

        // Create new Checkout Session for the order
        try {
            $checkout_session = $stripe->checkout->sessions->create([
                'line_items' => [[
                    'price_data' => [
                        'product_data' => [
                            'name' => $productName,
                            'metadata' => [
                                'pro_id' => $productID
                            ]
                        ],
                        'unit_amount' => $stripeAmount,
                        'currency' => $currency,
                    ],
                    'quantity' => $quantity
                ]],
                'mode' => 'payment',
                'success_url' => $success_redirect_url.'?redirect='.$url.'&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancel_redirect_url.'?redirect='.$url
            ]);
        } catch(\Exception $e) {
            $api_error = $e->getMessage();
            log_stripe_pay_messages('warning', $api_error);
        }

        if(empty($api_error) && $checkout_session) {
            $response_data = array(
                'status' => 1,
                'message' => 'Checkout Session created successfully!',
                'sessionId' => $checkout_session->id
            );
        } else {
            $response_data = array(
                'status' => 0,
                'error' => array(
                    'message' => 'Checkout Session creation failed! '.$api_error
                )
            );
        }

        // Create a JSON response
        $response = new JsonResponse($response_data);

        return $response;

    }


    /**
     * Callback URL handling for Sendinblue API API.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   Request.
     *
     * @return array
     *   Return markup for the page.
     */
    public function payment_success()
    {
		
        $session_id = \Drupal::request()->query->get('session_id');

        $redirect_url = '';

        // Invoke the custom hook for success redirect.
        \Drupal::moduleHandler()->invokeAll('stripe_pay_success_redirect', [&$redirect_url]);

        $message = '';

        // Invoke the custom hook for cancel message.
        \Drupal::moduleHandler()->invokeAll('stripe_pay_success_message', [&$message]);

        if($message == '') {
           /* $message =  '<strong>Payment success!</strong> Your transaction has been successed with session_id: '.$session_id.'.';*/
            $message =  '<strong>Payment success!</strong> Your order has been placed successfully, Please check your email for more information';
        }

        \Drupal::messenger()->addMessage($this->t($message));

        if($redirect_url == '') {
            $redirect_url = \Drupal::request()->query->get('redirect');
            if($redirect_url == '') {
                $redirect_url = Url::fromRoute('<front>', [], ['absolute' => true])->toString();
            }
        }
		
		if(isset($redirect_url) && !empty($redirect_url)) {
            $product_id=end(explode("/",$redirect_url));
			$this->order_create($product_id);
        }
		
        if($session_id != '') {
            $response = new TrustedRedirectResponse($redirect_url);
            // Redirect to the external URL
            $response->send();
        } else {
            $response = new TrustedRedirectResponse($redirect_url);
            $response->send();
        }
    }

    /**
     * Callback URL handling for Sendinblue API API.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *   Request.
     *
     * @return array
     *   Return markup for the page.
     */
    public function payment_cancel()
    {

        $cancel_redirect_url = '';

        // Invoke the custom hook for cancel redirect.
        \Drupal::moduleHandler()->invokeAll('stripe_pay_cancel_redirect', [&$cancel_redirect_url]);

        if($cancel_redirect_url == '') {
            $cancel_redirect_url = \Drupal::request()->query->get('redirect');
            if($cancel_redirect_url == '') {
                $cancel_redirect_url = Url::fromRoute('<front>', [], ['absolute' => true])->toString();
            }
        }

        $message = '';

        // Invoke the custom hook for cancel message.
        \Drupal::moduleHandler()->invokeAll('stripe_pay_cancel_message', [&$message]);

        if($message == '') {
            $message =  '<strong>Payment Canceled!</strong> Your transaction has been canceled.';
        }

        \Drupal::messenger()->addMessage($this->t($message));

        $response = new TrustedRedirectResponse($cancel_redirect_url);
        $response->send();

    }
	
	
	public function order_create($product_id=null)
    {
		$container = \Drupal::getContainer();
		$current_store = $container->get('commerce_store.current_store');
		$default_currency = $current_store->getStore()->getDefaultCurrency();
		$currency_code = $default_currency->getCurrencyCode();
		
		$entityTypeManager = \Drupal::entityTypeManager();
		/*$product = $entityTypeManager->getStorage('commerce_product')->load($product_id);*/
		$product = \Drupal\commerce_product\Entity\Product::load($product_id);
		$stripe_payment =$product->field_stripe_payment->stripe_payment;
		
		
		if($product){
			$product_title = $product->title;
		
			// Get the current user account.
			$user = \Drupal::currentUser();

			// Create an order for the current user.
			$order = Order::create([
			  'type' => 'default', // Replace with your order type if different.
			  'store_id' => 1, // Replace with the ID of your store.
			  'uid' => $user->id(),
			  'state' => 'completed', // The initial state of the order (e.g., 'draft', 'cart', 'completed').
			]);

			// Save the order.
			$order->save();

			// Create an order item for the product (without specifying a variation).
			$quantity = 1; // Replace with the quantity.
			$unit_price = new Price($stripe_payment, $currency_code); // Replace with the custom price and currency.

			$order_item = OrderItem::create([
			  'type' => 'default', // Replace with your order item type if different.
			  'order_id' => $order->id(),
			  'title' => $product_title,
			  'quantity' => $quantity,
			  'unit_price' => $unit_price,
			  'purchased_entity' => $product,
			]);

			// Save the order item.
			$order_item->save();

			// Add the order item to the order.
			$order->addItem($order_item);

			// Save the order to update it with the added order item.
			
			$order->recalculateTotalPrice();
			$order->save();
			
			$order->set('order_number', $order->id());
			$order->save();
			
			// Create a payment entity.
			/*$payment = Payment::create([
			  'payment_gateway' => 'stripe_pay', // Replace with your payment gateway ID.
			  'order_id' => $order->id(), // Replace with the actual order ID to which this payment belongs.
			]);

			// Save the payment entity.
			$payment->save();
			
			
			$order->set('payment', $payment->id());*/
			$order->save();
			
			
		}
    }
	
	
}
