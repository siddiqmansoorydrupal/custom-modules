<?php

namespace Drupal\custom_fixes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class DeleteNodeForm.
 *
 * @package Drupal\custom_fixes\Form
 */
class UpdateProductForm extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'update_custom_product_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['update_custom_product'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Update Products'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = \Drupal::entityQuery('commerce_product');
    //$query->condition('field_product_size_taxonomy', '', '=');
    $query->condition('status', 1);
	$query->accessCheck(FALSE);
    $product_ids = $query->execute();

    $products = array_chunk($product_ids, 10);

    foreach ($products as $product) {
      $operations[] = [
        '\Drupal\custom_fixes\UpdateProducts::updateProduct',
        [$product],
      ];
    }

    $batch = array(
      'title' => t('Updating Products...'),
      'operations' => $operations,
      'finished' => '\Drupal\custom_fixes\UpdateProducts::updateProductFinishedCallback',
    );

    batch_set($batch);
  }

}