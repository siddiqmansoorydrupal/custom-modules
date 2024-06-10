<?php

namespace Drupal\custom_fixes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Drupal\commerce_product\Entity\Product;

/**
 * ImporShippingTrackingForm class.
 */
class ProductCrossRefrence extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'product_cross_refrence_form';
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
  
        $form = array(
          '#attributes' => array('enctype' => 'multipart/form-data'),
        );
        
        $form['file_upload_details'] = array(
          '#markup' => t('<b>The File</b>'),
        );
        
        $validators = array(
          'file_validate_extensions' => array('csv'),
        );
        $form['excel_file'] = array(
          '#type' => 'managed_file',
          '#name' => 'excel_file',
          '#title' => t('File *'),
          '#size' => 20,
          '#description' => t('Excel format only'),
          '#upload_validators' => $validators,
          '#upload_location' => 'public://content/excel_files/',
        );
        
        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = array(
          '#type' => 'submit',
          '#value' => $this->t('Save'),
          '#button_type' => 'primary',
        );
    
        return $form;
    
    }

    /**
     * {@inheritdoc}
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
      $file = \Drupal::entityTypeManager()->getStorage('file')
                  ->load($form_state->getValue('excel_file')[0]);    
      $full_path = $file->get('uri')->value;
      $file_name = basename($full_path);

      $inputFileName = \Drupal::service('file_system')->realpath('public://content/excel_files/'.$file_name);
  
      $spreadsheet = IOFactory::load($inputFileName);
      
      $sheetData = $spreadsheet->getActiveSheet();
      
      $rows = array();
      foreach ($sheetData->getRowIterator() as $row) {
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE); 
        $cells = [];
        foreach ($cellIterator as $cell) {
          $cells[] = $cell->getValue();
        }
            $rows[] = $cells; 
      }

      unset($rows[0]);
      $products = array_chunk($rows, 5);
      foreach ($products as $product) {
        $operations[] = [
            '\Drupal\custom_fixes\Form\ProductCrossRefrence::importCrossRefrence',
            [$product],
        ];
      }

      $batch = array(
        'title' => t('Updating Products...'),
        'operations' => $operations,
        'finished' => '\Drupal\custom_fixes\Form\ProductCrossRefrence::importCrossRefrenceFinished',
      );

      batch_set($batch);
    }

    /**
     * {@inheritdoc}
     */
    public static function importCrossRefrence($products, &$context){
      $message = 'Updating products...';
      $results = array();
      $address_book = \Drupal::service('commerce_order.address_book');
      foreach ($products as $product) {
        $query = \Drupal::entityQuery('commerce_product');
        $query->condition('field_supplier_sku', $product[0]);
        $query->condition('status', 1);
		$query->accessCheck(FALSE);
        $product_ids = $query->execute();
        if (!empty($product_ids)) {
            $product_id = '';
            $product_id = reset($product_ids);
        }
        $commerce_product = Product::load($product_id);
        unset($product[0]);
        $cross_ref = $product;
        if ($commerce_product->hasField('commerce_price') && !empty($commerce_product->get('commerce_price')->getValue())) {
          if (!empty($cross_ref)) {
            $commerce_product->field_part_cross_reference = [];
            foreach ($cross_ref as $cross_ref_value) {
              if (!empty($cross_ref_value)) {
                $commerce_product->field_part_cross_reference[] = $cross_ref_value;
                // $variations = $commerce_product->getVariations();
                // foreach ($variations as $key => $value) {
                //   $amount = $value->getPrice();
                //   if (!$amount) {
                //     $variation = \Drupal\commerce_product\Entity\ProductVariation::load($value->get('variation_id')->getString());
                //     $product_price = reset($commerce_product->get('commerce_price')->getValue());
                //     $price = new \Drupal\commerce_price\Price($product_price['number'], 'USD');
                //     $variation->price = $price;
                //     $variation->uid = 1;
                //     $variation->status = 1;
                //     $variation->default_langcode = 1;
                //     $variation->save();
                //   }
                // }
                $commerce_product->save();
              }
            }
          }
        }
      }
    }

    /**
     * {@inheritdoc}
     */
    function importCrossRefrenceFinished($success, $results, $operations) {
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
