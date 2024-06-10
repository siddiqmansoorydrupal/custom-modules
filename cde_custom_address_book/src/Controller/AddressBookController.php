<?php

namespace Drupal\cde_custom_address_book\Controller;

use Drupal\Core\Controller\ControllerBase;

class AddressBookController extends ControllerBase {
  public function shippingPage($user) {
    // Render the My Account tabs
    $tabs_output = $this->renderTabs();

    // Your shipping page logic here
    $shipping_content = [
      '#markup' => $this->t('This is the Shipping Address Book page for user @user.', ['@user' => $user]),
    ];

    // Combine tabs and content
    return [
      'tabs' => $tabs_output,
      'content' => $shipping_content,
    ];
  }

  public function billingPage($user) {
    // Render the My Account tabs
    $tabs_output = $this->renderTabs();

    // Your billing page logic here
    $billing_content = [
      '#markup' => $this->t('This is the Billing Address Book page for user @user.', ['@user' => $user]),
    ];

    // Combine tabs and content
    return [
      'tabs' => $tabs_output,
      'content' => $billing_content,
    ];
  }

  private function renderTabs() {
    // Load the My Account tabs block
    $block = \Drupal\block\Entity\Block::load('system_main_block');
    if ($block) {
      $tabs_output = \Drupal::entityTypeManager()
        ->getViewBuilder('block')
        ->view($block);
      return $tabs_output;
    }
    return [];
  }
}
