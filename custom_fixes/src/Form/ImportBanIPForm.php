<?php

namespace Drupal\custom_fixes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Drupal\commerce_product\Entity\Product;

/**
 * ImporShippingTrackingForm class.
 */
class ImportBanIPForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
      return 'import_ban_ip_form';
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
          '\Drupal\custom_fixes\Form\ImportBanIPForm::importBanIP',
          [$product],
      ];
    }

    $batch = array(
      'title' => t('Updating Products...'),
      'operations' => $operations,
      'finished' => '\Drupal\custom_fixes\Form\ImportBanIPForm::importBanIPFinished',
    );

    batch_set($batch);
  }

  /**
   * {@inheritdoc}
   */
  public static function importBanIP($ips, &$context){
    $message = 'Updating products...';
    $results = array();
    $ip_manager = \Drupal::service('ban.ip_manager');
    foreach ($ips as $ip) {
      if (!empty($ip[1])) {
        if ($ip_manager->isBanned($ip[1])) {
          \Drupal::logger('ip_manager_notice')->notice('<pre><code>' . print_r('This IP address is already banned.', TRUE) . '</code></pre>' );
        }else{
          $ip_manager->banIp($ip[1]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  function importBanIPFinished($success, $results, $operations) {
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
