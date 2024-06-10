<?php

namespace Drupal\custom_fixes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionForm;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\user\Entity\User;
use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;
use Drupal\commerce_price\Price;

/**
 * RequestUpdateReplicate class.
 */
class RequestUpdateReplicate extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'request_update_replicate_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL)
  {

    $product_id = $options['product_id'];
    $product = \Drupal\commerce_product\Entity\Product::load($product_id);
    //\Drupal::logger('text')->info('<pre><code>' . print_r($product->get('field_category_taxonomy')->first()->getValue()['target_id'], true) . '</code></pre>');
    //dump($product);
    if ($product->field_image_commerce_product->entity) {
      $image_uri = $product->field_image_commerce_product->entity->getFileUri();
      if ($image_uri) {
        $imagepath = ImageStyle::load('thumbnail')->buildUrl($image_uri);
      } elseif ($product->field_category_taxonomy->entity) {
        $field_category_taxonomy = $product->get('field_category_taxonomy')->first()->getValue()['target_id'];

        $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($field_category_taxonomy);
        $image_uri = $term_obj->field_product_image->entity->getFileUri();
        if ($image_uri) {
          $imagepath = ImageStyle::load('thumbnail')->buildUrl($image_uri);
        } else {
          $imagepath = '/themes/custom/cde_subtheme/img/image-preview-icon-picture-placeholder-vector-31284806.jpg';
        }
      } else {
        $imagepath = '/themes/custom/cde_subtheme/img/image-preview-icon-picture-placeholder-vector-31284806.jpg';
      }

    } elseif ($product->field_category_taxonomy->entity) {
      $field_category_taxonomy = $product->get('field_category_taxonomy')->first()->getValue()['target_id'];

      $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($field_category_taxonomy);
      $image_uri = $term_obj->field_product_image->entity->getFileUri();
      if ($image_uri) {
        $imagepath = ImageStyle::load('thumbnail')->buildUrl($image_uri);
      } else {
        $imagepath = '/themes/custom/cde_subtheme/img/image-preview-icon-picture-placeholder-vector-31284806.jpg';
      }
    } else {
      $imagepath = '/themes/custom/cde_subtheme/img/image-preview-icon-picture-placeholder-vector-31284806.jpg';
    }
    $supplier_sku = $product->field_supplier_sku->value;
    $supplier_name = '';
    if ($product->hasField('field_supplier_reference')) {
      //$suppliers = $product->get('field_supplier_reference')->referencedEntities();
      if ($product->field_supplier_reference->entity) {
        $entity_id = $product->get('field_supplier_reference')->first()->getValue()['target_id'];
        $supplier_obj = \Drupal::entityTypeManager()->getStorage('node')->load($entity_id);
        $supplier_name = $supplier_obj->title->value;
      }
    }

    $form['#prefix'] = '<div id="request_update_modal_form"><h2>Broken Box - Replicate Product</h2><p class="product-description"><img src="' . $imagepath . '"><strong>Product:</strong> ' . $product->getTitle() . '<br><strong>Box Count:</strong> ' . number_format($product->get('field_quantity_per_box')->getString(), 0) . '<br>&nbsp;</p>';
    $form['#suffix'] = '</div>';
    $form['no_of_pieces'] = [
      '#type' => 'number',
      '#default_value' => 1,
      '#title' => $this->t('How many pieces are in the broken box? '),
      '#required' => TRUE,
    ];
    $form['product_id'] = [
      '#type' => 'hidden',
      '#default_value' => $product_id,
    ];
    $form['actions'] = array('#type' => 'actions');
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'formtarget' => '_blank',
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state)
  {
    $response = new AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#request_update_modal_form', $form));
    } else {
      $form_values = $form_state->getValues();

      $replicator = \Drupal::service('replicate.replicator');
      $clone_entity = $replicator->replicateByEntityId('commerce_product', $form_values['product_id']);
      $no_of_pieces = $form_values['no_of_pieces'];
      $title = $clone_entity->getTitle();
      $product_sku = $clone_entity->get('field_supplier_sku')->getString();
      $field_solr_text = $clone_entity->get('field_solr_text')->getString();

      //\Drupal::logger('avinash')->info('<pre><code>' . print_r($clone_entity->get('field_quantity_per_box')->getString(), true) . '</code></pre>');
      $field_quantity_per_box = $clone_entity->get('field_quantity_per_box')->getString();


      $price_per_box = ($clone_entity->get('commerce_price')->getString() / $field_quantity_per_box);
      //$five_price_per_box = ($clone_entity->get('field_5price')->getString() / $field_quantity_per_box);
      $weight_per_box = ($clone_entity->get('field_weight')->getString() / $field_quantity_per_box);

      // New prices
      $new_price_per_box = $no_of_pieces * $price_per_box;
      //$new_five_price_per_box = $no_of_pieces * $five_price_per_box;
      $new_weight_per_box = $no_of_pieces * $weight_per_box;

      // Set New Prices
      $clone_entity->set('commerce_price', new Price(round($new_price_per_box, 2), 'USD'));
      $clone_entity->set('field_5price', '');
      $clone_entity->set('field_weight', $new_weight_per_box);
      $clone_entity->set('field_quantity_per_box', $no_of_pieces);
      
      // Set Expiration date and offlinnne item status (expiration date of tomorrow and buttton status active)
      $clone_entity->set('field_expiration_date', date('Y-m-d', \Drupal::time()->getRequestTime() + 24 * 60 * 60));  
      $clone_entity->set('field_offline_item_status', 'active');
      $new_sku = next_value_sku_field_broaken($product_sku);
      $clone_entity->set('field_supplier_sku', $new_sku);
      $clone_entity->set('field_tele_part', '');
      $clone_entity->set('field_part_cross_reference', []);
      $clone_entity->set('field_live_item', 'N');
      $clone_entity->set('field_in_stock', 'O');
      $clone_entity->set('field_offline_item', '1');
      $clone_entity->set('field_solr_text', $field_solr_text .' ' .$new_sku);
      $request_time = \Drupal::time()->getRequestTime();
      $clone_entity->setCreatedTime($request_time); 
      $clone_entity->setChangedTime($request_time); 


     // Set New Product variation 
      $new_variation = \Drupal\commerce_product\Entity\ProductVariation::create([
        'type' => 'kanebridge_products', // Replace with your variation type.
        'sku' => $new_sku, // Unique SKU for the new variation.
        'price' => new Price(round($new_price_per_box,2), 'USD'),
        'status' => 1, // Set to 1 for active.
        'product_id' => $clone_entity->id(),
      ]);
      
      $new_variation->save();
      unset($clone_entity->variations);
      $clone_entity->variations[] = $new_variation;
      $clone_entity->save();

      $site_url = \Drupal::request()->getBaseUrl();
      $product_edit = $site_url . '/product/' . $clone_entity->id() . '/edit';
      $response->addCommand(new RedirectCommand($product_edit));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

  }



}