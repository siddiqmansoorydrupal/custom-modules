<?php

namespace Drupal\cde_net30_payment\Plugin\Commerce\Condition;

use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the customer role condition for orders.
 *
 * @CommerceCondition(
 *   id = "order_customer_net_eligible_taxonomy",
 *   label = @Translation("Customer net eligible by taxonomy"),
 *   category = @Translation("Customer"),
 *   entity_type = "commerce_order",
 *   weight = -1,
 * )
 */
class NetEligibleTaxonomy extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'net_eligible' => null,
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

    $form['net_eligible'] = [
      '#type' => 'radios',
      '#title' => $this->t('Net Eligible by taxonomy'),
      '#default_value' => $this->configuration['net_eligible'],
      '#options' => $net_eligible_options,
      '#required' => TRUE,
      '#description' => empty($net_eligible_options) ? $this->t("<div class='admin-missing'>*No terms found in taxonomy term <b>net_eligible</b></div>") : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $values = $form_state->getValue($form['#parents']);
    $this->configuration['net_eligible'] = $values['net_eligible'];
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    $customer = $order->getCustomer();
    $term = $this->configuration['net_eligible'];
    $userTerms = $customer->field_net_eligible->getValue();
    $userTermSelected = array_map(function ($term) {
      return $term['target_id'];
    }, $userTerms);
    return in_array($term, $userTermSelected);
  }

}
