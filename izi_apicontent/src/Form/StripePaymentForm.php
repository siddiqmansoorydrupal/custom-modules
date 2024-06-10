<?php

// File: src/Form/StripePaymentForm.php
namespace Drupal\izi_apicontent\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class StripePaymentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stripe_payment_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Include the Stripe.js library.
    $form['#attached']['library'][] = 'izi_apicontent.izi-stripe-js';

    // Name field.
    $form['customer_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name'),
      '#required' => TRUE,
    ];

    // Email field.
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
    ];

    // Address field.
    $form['address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Address'),
    ];

    // Country field.
    $form['country'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country'),
    ];

    // Postal code field.
    $form['postal_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Postal code'),
    ];

    // Description field.
    $form['notes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Description'),
    ];

    // Amount field.
    $form['price'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Amount'),
    ];

    // Currency field.
    $form['currency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currency'),
    ];

    // Card element placeholder.
    $form['card_element'] = [
      '#markup' => '<div id="card-element"><!-- Stripe.js injects the Card Element --></div>',
    ];

    // Submit button.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Payment'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Handle form submission.
  }
}
