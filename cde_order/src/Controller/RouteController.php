<?php

namespace Drupal\cde_order\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RouteController extends ControllerBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new RouteController object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * Controller method to handle the custom route.
   */
  public function orderEmail() {
    // Get the current request.
    $request = $this->requestStack->getCurrentRequest();
    $order = $request->attributes->get('commerce_order');
    $state = $order->get('state')->getString();
	  $net_30_status = $order->get('field_net_30_status')->getString();

	  if ($order->hasField('field_net_30_status') && ($net_30_status == 'net30_open' || $net_30_status == 'net30_paid' )) {
      if ($state == 'fulfillment') {
        $email_type_admin = 'net_30_order_complete_to_admin';
        $email_key_admin = 'new_30_on_order_complete_to_admin_custom';

        $email_type_user = 'net_30_order_complete_to_user';
        $email_key_user = 'net_30_on_order_complete_to_user_custom';

      }
      else {
        $email_type_admin = 'net_30_order_shipping_confirmation_to_admin';
        $email_key_admin = 'cde_net30_commerce_email_shipping_confirmation_admin';
          $email_type_user = 'ne_30_order_shipping_confirmation_to_user';
        $email_key_user = 'cde_net30_commerce_email_shipping_confirmation_user';
      }
    }
    else {
      if ($state == 'fulfillment') {
        $email_type_admin = 'on_order_complete_to_admin';
        $email_key_admin = 'on_order_complete_to_admin_custom';
        $email_type_user = 'on_order_complete_to_user';
        $email_key_user = 'on_order_complete_to_user_custom';
      }
      else {
        $email_type_admin = 'order_shipping_confirmation_to_admin';
        $email_key_admin = 'commerce_email_shipping_confirmation_admin';
        $email_type_user = 'order_shipping_confirmation_to_user';
        $email_key_user = 'commerce_email_shipping_confirmation_user';
      }
    }

    // You might want to perform some validation or manipulation of the destination value.
    // Perform the redirect.
    $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');
    $on_order_complete_to_admin = $email_storage->load($email_type_admin);
    if ($on_order_complete_to_admin) {
      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'cde_order';
      $key = $email_key_admin;
      $site_mail = \Drupal::config('system.site')->get('mail');
      $to = $site_mail;
      $token_service = \Drupal::token();
      $token_data = [
        'commerce_order' => $order,
      ];
      $token_options = ['clear' => TRUE];
      $params['message'] = $token_service->replace($on_order_complete_to_admin->getBody(), $token_data, $token_options);
      $params['order_id'] = $order->id();
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL);
      if ($result['result'] !== TRUE) {
        \Drupal::messenger()->addMessage('There was a problem sending your message and it was not sent.');
      }
      else {
        \Drupal::messenger()->addMessage('Resend reciept mail has been sent to Admin');
      }
    }

    // Shipping confirmation to User
    $order_shipping_confirmation_to_user = $email_storage->load($email_type_user);
    if ($order_shipping_confirmation_to_user) {
      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'cde_order';
      $key = $email_key_user;
      $to = $order->getEmail();
      $token_service = \Drupal::token();
      $token_data = [
        'commerce_order' => $order,
        '[site:url]' => \Drupal::request()->getSchemeAndHttpHost(),
      ];
      $token_options = ['clear' => TRUE];
      $email_body = '';
      if (!empty($order_shipping_confirmation_to_user->getBody())) {
        $email_body = $order_shipping_confirmation_to_user->getBody();
      }
      $params['message'] = $token_service->replace($email_body, $token_data, $token_options);
      $params['order_id'] = $order->id();
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL);
      if ($result['result'] !== TRUE) {
        \Drupal::messenger()->addMessage('There was a problem sending your message and it was not sent.');
      }
      else {
        \Drupal::messenger()->addMessage('Resend reciept mail has been sent to user.');
      }
    }

    $destination = \Drupal::request()->query->get('destination');
    return new RedirectResponse($destination);
  }

}
