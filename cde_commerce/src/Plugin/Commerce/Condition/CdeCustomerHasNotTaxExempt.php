<?php

namespace Drupal\cde_commerce\Plugin\Commerce\Condition;

use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides the customer has not tax exempt condition for orders.
 *
 * @CommerceCondition(
 *   id = "cde_customer_has_not_tax_exempt",
 *   label = @Translation("Customer has not tax exempt"),
 *   category = @Translation("Customer"),
 *   entity_type = "commerce_order",
 *   weight = -1,
 * )
 */
class CdeCustomerHasNotTaxExempt extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;

    $customer = $order->getCustomer();
    if ($customer->get('field_user_tax_exempt')->isEmpty()) {
      return TRUE;
    }

    $field_user_tax_exempt = $customer->get('field_user_tax_exempt')->value;
    if ($field_user_tax_exempt) {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

}
