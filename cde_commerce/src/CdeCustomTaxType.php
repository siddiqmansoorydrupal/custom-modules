<?php

namespace Drupal\cde_commerce;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\Custom;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\commerce_tax\TaxRate;

/**
 * Altering the 'custom' commerce tax type plugin.
 */
class CdeCustomTaxType extends Custom {

  /**
   * {@inheritdoc}
   */
  protected function getDisplayLabels() {
    $display_labels = parent::getDisplayLabels();

    $custom_labels = [
      'industrial_zone_tax' => $this->t('Industrial Tax'),
      'new_jersey_sales_tax' => $this->t('New Jersey Tax Rate'),
      'new_york_sales_tax' => $this->t('New York Tax Rate'),
    ];

    return array_merge($display_labels, $custom_labels);
  }

  /**
   * {@inheritdoc}
   */
  protected function resolveRates(OrderItemInterface $order_item, ProfileInterface $customer_profile) {
    $rates = parent::resolveRates($order_item, $customer_profile);

    if ($this->parentEntity->id() === 'industrial_zone_tax') {
      return $this->getCustomerIndustrialZoneTax($order_item, $rates);
    }
    else {
      return $rates;
    }
  }

  /**
   * Gets the tax rate for the consumer's industrial zone.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param array $rates
   *   The default tax rate defined in the plugin.
   *
   * @return array
   *   The tax rates for the consumer's industrial zone.
   */
  protected function getCustomerIndustrialZoneTax(OrderItemInterface $order_item, array $rates) {
    $new_rates = [];
    $order = $order_item->getOrder();
    $customer = $order->getCustomer();
    $field_industrail_tax_percent = (int) $customer->get('field_industrail_tax_percent')->value;
    $customer_tax_percent = $field_industrail_tax_percent / 100;

    foreach ($rates as $key => $rate) {
      $new_rate_array = $rate->toArray();
      $percentages = [];
      foreach ($new_rate_array['percentages'] as $percentage) {
        $new_percentage = $percentage;
        $new_percentage['number'] = (string) $customer_tax_percent;
        $percentages[] = $new_percentage;
      }
      $new_rate_array['percentages'] = $percentages;
      $new_rates[$key] = new TaxRate($new_rate_array);
    }

    return $new_rates;
  }

}
