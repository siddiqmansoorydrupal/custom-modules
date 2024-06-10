<?php

namespace Drupal\cde_commerce\Plugin\Commerce\Condition;

use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides the customer has industrial zone tax condition for orders.
 *
 * @CommerceCondition(
 *   id = "cde_customer_has_industrial_zone_tax",
 *   label = @Translation("Customer has industrial zone tax"),
 *   category = @Translation("Customer"),
 *   entity_type = "commerce_order",
 *   weight = -1,
 * )
 */
class CdeCustomerHasIndustrialZoneTax extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;

    $customer = $order->getCustomer();
    if ($customer->get('field_industrial_zone_tax')->isEmpty() ||
      $customer->get('field_industrail_tax_percent')->isEmpty()) {
      return FALSE;
    }

    $field_industrial_zone_tax = $customer->get('field_industrial_zone_tax')->value;
    if (!$field_industrial_zone_tax) {
      return FALSE;
    }

    return TRUE;
  }

}
