<?php

namespace Drupal\custom_fixes\Controller;
use Drupal\Core\Controller\ControllerBase;

use Drupal\commerce_product\Entity\ProductInterface;



/**
 * Class ProductVariationFixController.
 */
class DeleteOfflineProduct extends ControllerBase
{

    /**
     * Main.
     *
     * @return string
     *   Return Hello string.
     */
    public function DeleteProduct()
    {
         //$product_id = 51931; // Replace with the actual product ID you want to delete.

         $result = \Drupal::database()->query('SELECT DISTINCT commerce_product_field_data.product_id FROM commerce_product_field_data LEFT JOIN commerce_product__field_offline_item ON commerce_product_field_data.product_id = commerce_product__field_offline_item.entity_id LEFT JOIN commerce_product__field_offline_item_status commerce_product__field_offline_item_status ON commerce_product_field_data.product_id = commerce_product__field_offline_item_status.entity_id WHERE commerce_product__field_offline_item.field_offline_item_value = 1 AND commerce_product__field_offline_item_status.field_offline_item_status_value IS NULL')->fetchAll();
   //dump($result);
  
   $_prod_array = [
    1000124,
    1000123,
    1000122,
    1000121,
    1000120,
    1000119,
    1000118,
    1000117,
    1000116,
   ];
     foreach($result as $key => $product_id) {
     //print $product_id['product_id'] ;
    //  print $result[$key]->product_id;
    //   print "<br>" ; 
    
     if(in_array($result[$key]->product_id, $_prod_array)) {
         continue; 
        
      }
       $product = \Drupal::entityTypeManager()->getStorage('commerce_product')->load($result[$key]->product_id);
        if ($product instanceof ProductInterface) {
            $product->delete();

            \Drupal::messenger()->addMessage($this->t('Deleted'));
        } else {
            \Drupal::messenger()->addMessage($this->t('Not Deleted'));
        }
    
    

     }

        // $product = \Drupal::entityTypeManager()->getStorage('commerce_product')->load($product_id);
        // if ($product instanceof ProductInterface) {
        //     $product->delete();

        //     \Drupal::messenger()->addMessage($this->t('Deleted'));
        // } else {
        //     \Drupal::messenger()->addMessage($this->t('Not Deleted'));
        // }
        return [
            '#markup' => 'Deleting product',
          ];
    }

}