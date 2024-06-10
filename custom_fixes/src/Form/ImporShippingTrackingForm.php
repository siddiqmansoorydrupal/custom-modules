<?php

namespace Drupal\custom_fixes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
// use Drupal\Core\Ajax\AjaxResponse;
// use Drupal\Core\Ajax\OpenModalDialogCommand;
// use Drupal\Core\Ajax\ReplaceCommand;
// use Drupal\webform\Entity\Webform;
// use Drupal\webform\WebformSubmissionForm;
// use Drupal\Core\Ajax\CloseModalDialogCommand;
// use Drupal\user\Entity\User;
// use Drupal\commerce_product\Entity\Product;


// use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
// use PhpOffice\PhpSpreadsheet\Style\Fill;
// use PhpOffice\PhpSpreadsheet\Cell\DataType;
// use PhpOffice\PhpSpreadsheet\Style\Alignment;
// use PhpOffice\PhpSpreadsheet\Style\Border;



/**
 * ImporShippingTrackingForm class.
 */
class ImporShippingTrackingForm extends FormBase {

    /**
     * {@inheritdoc}
     */
    public function getFormId() {
        return 'impor_shipping_tracking_form';
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
        $form['truncate'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('unset shipping details.'),
        ];

        $form['billing_information'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('import Billing details.'),
        ];
        
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

      $values = $form_state->getValues();
      $truncate = $values['truncate'];
      $billing_information = $values['billing_information'];

      $rows = array();
      foreach ($sheetData->getRowIterator() as $row) {
        //echo "<pre>";print_r($row);exit;
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(FALSE); 
        $cells = [];
        foreach ($cellIterator as $cell) {
          $cells[] = $cell->getValue();
        }
            $rows[] = $cells;
      }

      $orders = array_chunk($rows, 10);

      if ($truncate == 1) {
        foreach ($orders as $order) {
          $operations[] = [
              'truncate_order_track_number',
              [$order],
          ];
        }
      }
      elseif ($billing_information == 1) {
        foreach ($orders as $order) {
            $operations[] = [
                'import_order_billing_track_number',
                [$order],
            ];
        }
      }
      else{
        foreach ($orders as $order) {
            $operations[] = [
                'import_order_track_number',
                [$order],
            ];
        }
      }

      $batch = array(
          'title' => t('Updating Products...'),
          'operations' => $operations,
          'finished' => 'import_order_track_number_finished',
      );

      batch_set($batch);
    }
}