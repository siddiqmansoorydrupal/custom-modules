<?php

namespace Drupal\cde_migrate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a CSV import form.
 */
class CdeMigrateForm extends FormBase {

  protected $fileSystem;

  /**
   * Constructs a new CdeMigrateForm object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cde_migrate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Add your form elements here.
    // You can add a file upload field for the CSV file and any other form elements.
	
	$form['csv_file'] = [
	  '#type' => 'managed_file',
	  '#title' => t('Upload CSV File'),
	  '#upload_location' => 'public://csv/',
	  '#upload_validators' => [
		'file_validate_extensions' => ['csv'],
	  ],
	];
	
	/*$form['import_type'] = [
		'#type' => 'select',
		'#title' => $this->t('Select an option'),
		'#options' => [
		  'Master Kanebridge Product Importer' => $this->t('Master Kanebridge Product Importer'),
		  'Weekly Kanebridge Product Importer' => $this->t('Weekly Kanebridge Product Importer'),
		  'Offline Products' => $this->t('Offline Products'),
		],
		'#default_value' => 'option1', // Set a default value if needed.
	];*/
	
	// Add multiple submit buttons.
    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      /*'#submit' => ['::ImportForm'],*/
    ];

    return $form;
  }
  
	public function validateForm(array &$form, FormStateInterface $form_state) {
		// Add validation for your form elements, including CSV validation.
		// Check for the file type, size, and other custom validations.
	}

	/**
	 * Form submission handler.
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
		// Get the uploaded file and its temporary path.
		$validators = array('file_validate_extensions' => array('csv'));
		$file = $form_state->getValue('csv_file');

		$file_entity = \Drupal\file\Entity\File::load($file[0]);

		if ($file_entity) {
		  $temp_path = $file_entity->getFileUri();
		  // Now, $uri contains the file URI.
		} else {
		  $temp_path = '';
		}

		// Convert the CSV file to an array.
		$csv_array = $this->convertCsvToArray($temp_path);
		
		$success_products=$error_products=[];

		// Process the CSV data (e.g., print it).
		foreach ($csv_array as $row) {
			
		  $_product = $this->loadProductBySku($row['SKU']);
			if ($_product) {
				
				
				$image_path = $row['Image'];
				$fid = $this->cde_migrate_save_image_to_drupal($image_path);

				if ($fid) {
					$_product->set('field_image_commerce_product', $fid);
				}
				  
				$_product->save();
				$success_products[]=$row['SKU'];
			  
			}
			else {
				$error_products[]=$row['SKU'];
			}
			
		}
		
		if(count($success_products)>0){
			\Drupal::messenger()->addStatus(implode(", ", $success_products)." Updated Successfully");
		}

		if(count($error_products)>0){
			\Drupal::messenger()->addError(implode(", ", $error_products)." are invalid SKU");
		}
	}
	
	function cde_migrate_save_image_to_drupal($image_path) {
		
		$file_contents = file_get_contents($image_path);
		$destination = 'public://product_images/' . basename($image_path);
		$uri = $this->fileSystem->realpath($destination);
		file_put_contents($uri, $file_contents);
		$file = \Drupal::entityTypeManager()->getStorage('file')->create([
		  'uri' => $destination,
		]);
		$file->save();
		return $file ? $file->id() : NULL;
	}

	// Function to convert a CSV file to an array.
	private function convertCsvToArray($file_path) {
		$csv = \League\Csv\Reader::createFromPath($file_path);
		$csv->setHeaderOffset(0);
		$csv_array = iterator_to_array($csv->getRecords());
		return $csv_array;
	}
  
  
	// Load a product by its SKU.
	function loadProductBySku($sku) {
	  $product_storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
	  $query = $product_storage->getQuery()
		->condition('type', 'knebridge_product_nodes') // Adjust the product type if necessary.
		->condition('status', 1) // To load only active products.
		->condition('field_supplier_sku.value', $sku); // Replace 'field_sku' with the actual field name where SKU is stored.
	  $query->accessCheck(FALSE);
	  $product_ids = $query->execute();
	  if (!empty($product_ids)) {
		// Load the product entity.
		$product_id = reset($product_ids);
		$product = $product_storage->load($product_id);
		return $product;
	  }
	  else {
		// Product not found.
		return NULL;
	  }
	}
}
