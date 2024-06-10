<?php

namespace Drupal\cde_order\Plugin\Commerce\TaxType;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\Custom;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the CDE Tax type.
 *
 * @CommerceTaxType(
 *   id = "cde_tax",
 *   label = "CDE Tax",
 * )
 */
class CDETax extends Custom {

  /**
   * {@inheritdoc}
   */
  public function applies(OrderInterface $order) {
    $store = $order->getStore();
    $store_id = $store->get('store_id')->value;
    $config = $this->getConfiguration();
    $config_stores = $config['commerce_stores'];

    if (in_array($store_id, $config_stores)
      && $this->matchesAddress($store) || $this->matchesRegistrations($store)) {
      return true;
    }
  }

  /**
   * {@inheritdoc}
   *
   * Changed parent function to check for product variation type.
   */
  public function apply(OrderInterface $order) {
    $store = $order->getStore();
    $prices_include_tax = $store->get('prices_include_tax')->value;
    $matches_store_address = $this->matchesAddress($store);
    $zones = $this->getZones();
    foreach ($order->getItems() as $order_item) {
      $customer_profile = $this->resolveCustomerProfile($order_item);
      if (!$customer_profile) {
        continue;
      }

      $adjustments = $order_item->getAdjustments();
      $rates = $this->resolveRates($order_item, $customer_profile);
      // Don't overcharge a tax-exempt customer if the price is tax-inclusive.
      // A negative adjustment is added with the difference, and optionally
      // applied to the unit price in the TaxOrderProcessor.
      $negate = FALSE;
      if (!$rates && $prices_include_tax && $matches_store_address) {
        // The price difference is calculated using the store's default tax
        // type, but only if no other tax type added its own tax.
        // For example, a 12 EUR price with 20% EU VAT gets a -2 EUR
        // adjustment if the customer is from Japan, but only if no
        // Japanese tax was added due to a JP store registration.
        $positive_tax_adjustments = array_filter($adjustments, function ($adjustment) {
          /** @var \Drupal\commerce_order\Adjustment $adjustment */
          return $adjustment->getType() == 'tax' && $adjustment->isPositive();
        });
        if (empty($positive_tax_adjustments)) {
          $store_profile = $this->buildStoreProfile($store);
          $rates = $this->resolveRates($order_item, $store_profile);
          $negate = TRUE;
        }
      }
      else {
        // A different tax type added a negative adjustment, but this tax type
        // has its own tax to add, removing the need for a negative adjustment.
        $negative_tax_adjustments = array_filter($adjustments, function ($adjustment) {
          /** @var \Drupal\commerce_order\Adjustment $adjustment */
          return $adjustment->getType() == 'tax' && $adjustment->isNegative();
        });
        $adjustments = array_diff_key($adjustments, $negative_tax_adjustments);
        $order_item->setAdjustments($adjustments);
      }

      // Check for product variation type.
      $product_variation_type = $order_item->getPurchasedEntity()->bundle();

      if (isset($this->configuration['product_variation_types']) && is_array($this->configuration['product_variation_types'])) {
        if (in_array($product_variation_type, $this->configuration['product_variation_types'])) {
          foreach ($rates as $zone_id => $rate) {
            $zone = $zones[$zone_id];
            $unit_price = $order_item->getUnitPrice();
            $percentage = $rate->getPercentage();
            $tax_amount = $percentage->calculateTaxAmount($unit_price, $prices_include_tax);
            if ($this->shouldRound()) {
              $tax_amount = $this->rounder->round($tax_amount);
            }
            if ($prices_include_tax && !$this->isDisplayInclusive()) {
              $unit_price = $unit_price->subtract($tax_amount);
              $order_item->setUnitPrice($unit_price);
            }
            elseif (!$prices_include_tax && $this->isDisplayInclusive()) {
              $unit_price = $unit_price->add($tax_amount);
              $order_item->setUnitPrice($unit_price);
            }

            $order_item->addAdjustment(new Adjustment([
              'type' => 'tax',
              'label' => $zone->getDisplayLabel(),
              'amount' => $negate ? $tax_amount->multiply('-1') : $tax_amount,
              'percentage' => $percentage->getNumber(),
              'source_id' => $this->entityId . '|' . $zone->getId() . '|' . $rate->getId(),
              'included' => !$negate && $this->isDisplayInclusive(),
            ]));
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'product_type_variations' => [],
      'commerce_stores' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['product_variation_types'] = [
      '#type' => 'commerce_entity_select',
      '#target_type' => 'commerce_product_variation_type',
      '#title' => $this->t('Product variations'),
      '#description' => $this->t('Select product variations to apply tax to.'),
      '#default_value' => $this->configuration['product_variation_types'],
      '#hide_single_entity' => FALSE,
      '#autocomplete_threshold' => 10,
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];
    $form['commerce_stores'] = [
      '#type' => 'commerce_entity_select',
      '#target_type' => 'commerce_store',
      '#title' => $this->t('Commerce store'),
      '#description' => $this->t('Select store to apply tax to.'),
      '#default_value' => $this->configuration['commerce_stores'],
      '#hide_single_entity' => FALSE,
      '#autocomplete_threshold' => 10,
      '#multiple' => TRUE,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);

      $this->configuration['product_variation_types'] = $values['product_variation_types'];
      $this->configuration['commerce_stores'] = $values['commerce_stores'];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDisplayLabels() {
    return [
      'tax' => $this->t('Tax'),
      'vat' => $this->t('VAT'),
      // Australia, New Zealand, Singapore, Hong Kong, India, Malaysia.
      'gst' => $this->t('GST'),
      // Japan.
      'consumption_tax' => $this->t('Consumption tax'),
      'industrial_tax' => $this->t('Industrial Tax'),
      'new_jersey_tax_rate' => $this->t('New Jersey Tax Rate'),
      'new_york_tax_rate' => $this->t('New York Tax Rate'),
    ];
  }

}
