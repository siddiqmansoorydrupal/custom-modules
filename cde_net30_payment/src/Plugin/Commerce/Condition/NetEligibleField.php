<?php

namespace Drupal\cde_net30_payment\Plugin\Commerce\Condition;

use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the customer role condition for orders.
 *
 * @CommerceCondition(
 *   id = "order_customer_net_eligible_field",
 *   label = @Translation("Customer net eligible by field"),
 *   category = @Translation("Customer"),
 *   entity_type = "commerce_order",
 *   weight = -1,
 * )
 */
class NetEligibleField extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'net_eligible_fields' => null,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $net_eligible_terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'net_eligible'
    ]);

    $net_eligible_options = array_reduce($net_eligible_terms, function ($options, $term) {
      $options[$term->id()] = $term->label();
      return $options;
    }, []);

    $form['net_eligible_fields'] = [
      '#type' => 'radios',
      '#title' => $this->t('Net Eligible by Fields'),
      '#default_value' => $this->configuration['net_eligible_fields'],
      '#options' => [
        'field_net_30_eligible_' => $this->t("Net 30 Eligible"),
        'field_net_45_eligible_' => $this->t("Net 45 Eligible"),
        'field_net_60_eligible_' => $this->t("Net 60 Eligible"),
      ],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['net_eligible_fields'] = $values['net_eligible_fields'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $customer = $order->getCustomer();
    $field_name = $this->configuration['net_eligible_fields'];

    if (isset($customer->$field_name)) {
      $field_values = $customer->$field_name->getValue();
      return isset($field_values[0]['value']) && $field_values[0]['value'] === '1';
    }
  }

}
