<?php

namespace Drupal\custom_fixes;

use Drupal\commerce_product\Entity\Product;

class UpdateProducts {

  public static function updateProduct($products, &$context){
    $message = 'Updating Products...';
    $results = array();
    foreach ($products as $product) {
        $product =  Product::load($product);
        $is_offline = $product->get('field_offline_item')->getString();
        $old_solr_text = $product->get('field_solr_text')->getString();
        $supplier_sku = $product->get('field_supplier_sku')->getString();
        $product_title = $product->getTitle();
        $new_solr_text = $old_solr_text . ' ' .$product_title . ' ('. $supplier_sku .')  ' . $supplier_sku;
        
        if (strpos($old_solr_text, $supplier_sku) !== false) {
            //echo "$old_solr_text contains $needle";
        }
        else{
          $product->set('field_solr_text', $new_solr_text);
          $product->save();
        }


        // $product->set('field_solr_text', $new_solr_text);
        // if ($is_offline != 1 || $is_offline = '') {
        //   $product->set('field_offline_item', 0);
        // }
        // $product->save();
        //$product_size = $product->get('field_product_size')->getString();
        // if (!empty($product_size)) {
        //     $term_id = '';
        //     $properties = [];
        //     $properties['name'] = $product_size;
        //     $properties['vid'] = 'product_size';
        //     $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);
        //     $term = reset($terms);

        //     $term_id = !empty($term) ? $term->id() : 0;
        //     // \Drupal::logger('module_name_product_size')->notice('<pre><code>' . print_r($product_size, TRUE) . '</code></pre>' );
        //     \Drupal::logger('module_name_term_id')->notice('<pre><code>' . print_r($term_id, TRUE) . '</code></pre>' );
        //     $product->field_product_size_taxonomy[] = ['target_id' => $term_id];
        //     $product->save();
        // }
    }
    $context['message'] = $message;
    $context['results'] = $results;
  }

  function updateProductFinishedCallback($success, $results, $operations) {
    // The 'success' parameter means no fatal PHP errors were detected. All
    // other error management should be handled using 'results'.
    if ($success) {
      $message = \Drupal::translation()->formatPlural(
        count($results),
        'One post processed.', '@count posts processed.'
      );
    }
    else {
      $message = t('Finished with an error.');
    }
  }
}