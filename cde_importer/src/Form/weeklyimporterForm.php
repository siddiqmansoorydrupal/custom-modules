<?php
namespace Drupal\cde_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use League\Csv\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class weeklyimporterForm extends FormBase {
	
  public function getFormId() {
    return 'weekly_importer_form';
  }
 
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
	
	// Add the CSV export button.
	$form['export_csv'] = [
	  '#type' => 'submit',
	  '#value' => $this->t('Download CSV'),
	  '#submit' => ['::exportCsv'],
	];

    return $form;
  }
  
	/**
	* Submit function for exporting CSV.
	*/
	public function exportCsv(array &$form, FormStateInterface $form_state) {
		
		$fields_mapped=$this->loadProductAttributes();		
		
		// Get the entity type manager service.
		$entity_type_manager = \Drupal::entityTypeManager();
		
		// Load the product storage.
		$product_storage = $entity_type_manager->getStorage('commerce_product');
	
		// Define the number of products you want to retrieve.
		$limit = 5000;

		// Create an array to store the product data.
		$csv_data = [];

		// Query for products.
		$query = $product_storage->getQuery()
			->condition('type', 'knebridge_product_nodes')
			->condition('status', 1)
			->condition('field_offline_item', 1, "!=")
			->range(0, $limit);
		$query->accessCheck(FALSE);
		$product_ids = $query->execute();
		
		$heading=[];
		
		$heading[]='ITEM_NO';
		foreach($fields_mapped as $fields_mapped_key => $fields_mapped_val){
			$heading[$fields_mapped_key]=$fields_mapped_key;
		}
		
		$csv_data[] = $heading;
		 
		 
		// Load the products by their IDs.
		foreach ($product_ids as $product_id) {
		  /** @var ProductInterface $product */
		  $product = $product_storage->load($product_id);
		  //var_dump($product);die;
		  
		  // Extract the relevant information from the product entity.
			$_product=[];
			
			$_product['ITEM_NO']=$product->get('field_supplier_sku')->getString();
			
			
			foreach($fields_mapped as $fields_mapped_key => $fields_mapped_val){	
					
				$_field=$fields_mapped_val['field'];
				$_type=$fields_mapped_val['type'];
				$_taxonomy=$fields_mapped_val['taxonomy'];
				
				if(!empty($_type) && $_type=="price"){
					
					$price_field = $product->get($_field);
					$price_value = $price_field->getValue();
					$_product[$_field] = $price_value[0]['number'];
					
				}elseif(!empty($_taxonomy)){
					
					
					$taxonomy_term_reference_field = $product->get($_field);

					// Get the referenced taxonomy term.
					$term = $taxonomy_term_reference_field->entity;

					if ($term) {
						// You can now work with the referenced taxonomy term.
						$term_name = $term->getName();
						$term_id = $term->id();
						$_product[$_field]=$term_name;
					}else{
						$_product[$_field]=$product->get($_field)->getString();
					}
					
				}else{
					$_product[$_field]=$product->get($_field)->getString();
				}
			}
		  
		  $csv_data[] = $_product;
		}
		

	  // Create a CSV file and set its contents.
	  $csv_file = tempnam('public://csv', 'export_') . '.csv';
	  $handle = fopen($csv_file, 'w');
	  foreach ($csv_data as $row) {
		fputcsv($handle, $row);
	  }
	  fclose($handle);

	  // Serve the CSV file for download.
	  $response = new BinaryFileResponse($csv_file);
	  $form_state->setResponse($response);
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
		
		$fields_mapped=$this->loadProductAttributes();
		$success_products=$error_products=[];

		// Process the CSV data (e.g., print it).
		foreach ($csv_array as $row) {
			
		  $_product = $this->loadProductBySku($row['ITEM_NO']);
			if ($_product) {
				
				
				foreach($fields_mapped as $fields_mapped_key => $fields_mapped_val){	
			  
					if(array_key_exists($fields_mapped_key,$row)){
						
						$_value=$row[$fields_mapped_key];
						$_field=$fields_mapped_val['field'];
						$_type=$fields_mapped_val['type'];
						$_taxonomy=$fields_mapped_val['taxonomy'];
						
						if(!empty($_type) && $_type=="users"){
							
							$users_id = [];	
							
							$term_val = $_value;				
							if (strpos($term_val, ',') !== FALSE) {
							  $users_id_array = explode(",", $term_val);
							  foreach($users_id_array as $users_id_val){
								$users_id[] = trim($users_id_val);  
							  }
							  
							}
							else {
							  $users_id[] = trim($term_val);
							}
							
							$_product->set($_field, $users_id);
							
						}if(!empty($_type) && $_type=="price"){
							
							if(empty($_value)){
								$_value=0;
							}
							
							if (strpos($_value, ',') !== FALSE) {
							  $_value = str_replace(',', '', $_value);
							}
							
							
							$_amount = number_format($_value, 2, '.', '');
							
							$_product->get($_field)->setValue([
								'number' => $_amount,
								'currency_code' => 'USD',
							]);
						}elseif(!empty($_taxonomy)){
							
							$term_names = [];				
							$term_val = $_value;				
							if (strpos($term_val, '|') !== FALSE) {
							  $term_names = explode("|", $term_val);
							}
							else {
							  $term_names[] = $term_val;
							}
							
							$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
							
							$term_ids = [];
							$vocabulary = $_taxonomy;		

							// Iterate through the term names and load the corresponding terms.
							foreach ($term_names as $term_name) {
								
								if(!empty($term_name)){
									$term = $term_storage->loadByProperties(['name' => $term_name, 'vid' => $vocabulary]);
									if (!empty($term)) {
									  // Get the first term found (there might be multiple terms with the same name).
									  $term = reset($term);
									  $term_ids[] = $term->id();
									}
								}						
								
								
							}							
							
							//die;
							$_product->set($_field, $term_ids);
							
							
						}else{
							$_product->get($_field)->setValue($_value);							
						}
						
					}
				}
				  
				$_product->save();
				$success_products[]=$row['ITEM_NO'];
			  
			}
			else {
				$error_products[]=$row['ITEM_NO'];
			}
			
		}
		
		if(count($success_products)>0){
			\Drupal::messenger()->addStatus(implode(", ", $success_products)." Updated Successfully");
		}

		if(count($error_products)>0){
			\Drupal::messenger()->addError(implode(", ", $error_products)." are invalid SKU");
		}
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
		->condition('field_offline_item', 1, "!=")
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

	// Load a product by its SKU.
	function loadProductAttributes() {
	  
	  
		return $fields_mapped=[	

			'PRODUCT_CODE' => ['field'=>'field_product_code', 'type' => "", 'taxonomy'=>''],
			'TELE_PART' => ['field'=>'field_tele_part', 'type' => "", 'taxonomy'=>''],
			'DESCRIPTION' => ['field'=>'field_description_long_text', 'type' => "", 'taxonomy'=>''],
			'LONG_DESCRIPTION' => ['field'=>'field_long_description', 'type' => "", 'taxonomy'=>''],
			'DOMESTIC_IMPORT_IND' => ['field'=>'field_domestic_import', 'type' => "", 'taxonomy'=>''],
			'UM' => ['field'=>'field_unit_of_measure', 'type' => "", 'taxonomy'=>''],
			'QTY_BREAK_1' => ['field'=>'field_qty_break_1', 'type' => "", 'taxonomy'=>''],
			'PRICE_BREAK_1' => ['field'=>'field_price_break_1', 'type' => "", 'taxonomy'=>''],
			'PRICE_BREAK_3' => ['field'=>'field_price_break_3', 'type' => "", 'taxonomy'=>''],
			'WGHT_PER_UM' => ['field'=>'field_weight_per_um', 'type' => "", 'taxonomy'=>''],
			'BLUE_DEVIL_ITEM_NO' => ['field'=>'field_blue_devil_item_no', 'type' => "", 'taxonomy'=>''],
			'BLUE_DEVIL_PRICE' => ['field'=>'field_blue_devil_price', 'type' => "", 'taxonomy'=>''],
			'DATE_ADDED' => ['field'=>'field_date_added', 'type' => "", 'taxonomy'=>''],
			'LIVE ITEM' => ['field'=>'field_live_item', 'type' => "", 'taxonomy'=>''],
			'IN STOCK' => ['field'=>'field_in_stock', 'type' => "", 'taxonomy'=>''],
			'Product Size' => ['field'=>'field_product_size', 'type' => "", 'taxonomy'=>''],
			'Pieces Per LB' => ['field'=>'field_pieces_per_lb', 'type' => "", 'taxonomy'=>''],
			'Sort Size' => ['field'=>'field_sort_sizes', 'type' => "", 'taxonomy'=>''],
			'Product Field' => ['field'=>'field_product_field', 'type' => "", 'taxonomy'=>''],
			'Product Category' => ['field'=>'field_product_category_term', 'type' => "", 'taxonomy'=>'product_category'],
			'Item Description' => ['field'=>'field_item_description', 'type' => "", 'taxonomy'=>''],
			'Product Subcategory' => ['field'=>'field_category_taxonomy', 'type' => "", 'taxonomy'=>'products'],
			'Our Price' => ['field'=>'commerce_price', 'type' => "price", 'taxonomy'=>''],
			'5 Price' => ['field'=>'field_5price', 'type' => "price", 'taxonomy'=>''],
			'Our Weight' => ['field'=>'field_weight', 'type' => "", 'taxonomy'=>''],
			'Our Quantity' => ['field'=>'field_quantity_per_box', 'type' => "", 'taxonomy'=>''],
			
			/*'Metric / Standard' => ['field'=>'field_metric_standard', 'type' => "", 'taxonomy'=>'product_metric_standard'],
			'Metric Specification' => ['field'=>'field_metric_standard_', 'type' => "", 'taxonomy'=>'product_metric_standard_'],
			'Inner Diameter' => ['field'=>'field_inner_diameter', 'type' => "", 'taxonomy'=>'product_inner_diameter'],
			'Diameter' => ['field'=>'field_diameter', 'type' => "", 'taxonomy'=>'product_diameter'],
			'Thread Pitch' => ['field'=>'field_thread_pitch', 'type' => "", 'taxonomy'=>'product_thread_pitch'],
			'Length' => ['field'=>'field_length', 'type' => "", 'taxonomy'=>'product_length'],
			'Shaft Diameter' => ['field'=>'field_shaft_diameter', 'type' => "", 'taxonomy'=>'product_shaft_diameter'],
			'Outer Diameter' => ['field'=>'field_outer_diameter', 'type' => "", 'taxonomy'=>'product_outer_diameter'],
			'Washer Outer Diameter' => ['field'=>'field_washer_outer_diameter', 'type' => "", 'taxonomy'=>'product_washer_outer_diameter'],
			'Shank' => ['field'=>'field_shank', 'type' => "", 'taxonomy'=>'product_shank'],
			'Point' => ['field'=>'field_point', 'type' => "", 'taxonomy'=>''],
			'Hex Washer' => ['field'=>'field_hex_washer', 'type' => "", 'taxonomy'=>'product_hex_washer'],
			'Head Height' => ['field'=>'field_head_height', 'type' => "", 'taxonomy'=>''],			
			
			'Distance Across Flats' => ['field'=>'field_distance_across_flats', 'type' => "", 'taxonomy'=>'product_distance_across_flats'],
			'Tek Point' => ['field'=>'field_tek_point', 'type' => "", 'taxonomy'=>'product_tek_point'],
			'Grip Range' => ['field'=>'field_grip_range', 'type' => "", 'taxonomy'=>'product_grip_range'],
			'Thickness' => ['field'=>'field_thickness_taxonomy', 'type' => "", 'taxonomy'=>'product_thickness'],
			'Tab Base' => ['field'=>'field_tab_base', 'type' => "", 'taxonomy'=>''],
			
			'Base Diameter' => ['field'=>'field_base_diameter', 'type' => "", 'taxonomy'=>''],
			'Sub Sub Cat' => ['field'=>'field_sub_sub_cat', 'type' => "", 'taxonomy'=>'product_sub_sub_cat'],
			'Type' => ['field'=>'field_type', 'type' => "", 'taxonomy'=>'product_type'],
			'Head Style' => ['field'=>'field_head_style', 'type' => "", 'taxonomy'=>'product_head_style'],
			'Abnormal Head Diameter' => ['field'=>'field_abnormal_head_diameter', 'type' => "", 'taxonomy'=>'product_abnormal_head_diameter'],
			'Drive' => ['field'=>'field_drive', 'type' => "", 'taxonomy'=>'product_drive'],
			'Style' => ['field'=>'field_style', 'type' => "", 'taxonomy'=>'product_style'],		
			
			'Point Type' => ['field'=>'field_point_type', 'type' => "", 'taxonomy'=>'product_point_type'],
			'Washer Type' => ['field'=>'field_washer_type', 'type' => "", 'taxonomy'=>'product_washer_type'],
			'Thread Length' => ['field'=>'field_thread_length', 'type' => "", 'taxonomy'=>'product_thread_length'],
			'Thread Type' => ['field'=>'field_thread_type', 'type' => "", 'taxonomy'=>'product_thread_type'],
			'Weld Style' => ['field'=>'field_weld_style', 'type' => "", 'taxonomy'=>'product_weld_style'],
			'Military Specification' => ['field'=>'field_military_specification', 'type' => "", 'taxonomy'=>'product_military_specification'],
			'National Aerospace Standards' => ['field'=>'field_national_aero_space', 'type' => "", 'taxonomy'=>'product_national_aerospace_sta'],
			'Wings' => ['field'=>'field_wings', 'type' => "", 'taxonomy'=>'product_wings'],
			'Material' => ['field'=>'field_material', 'type' => "", 'taxonomy'=>'product_material'],
			'Finish' => ['field'=>'field_finish', 'type' => "", 'taxonomy'=>'product_finish'],
			'Internal Expander' => ['field'=>'field_internal_expander', 'type' => "", 'taxonomy'=>'internal_expander'],
			'External Sleeve' => ['field'=>'field_external_sleeve', 'type' => "", 'taxonomy'=>'product_external_sleeve'],
			'Body Material' => ['field'=>'field_body_material', 'type' => "", 'taxonomy'=>'product_body_material'],
			'Pin Material' => ['field'=>'field_pin_material', 'type' => "", 'taxonomy'=>'product_pin_material'],
			'Screw Material' => ['field'=>'field_screw_material', 'type' => "", 'taxonomy'=>'product_screw_material'],
			'Washer Material' => ['field'=>'field_washer_material', 'type' => "", 'taxonomy'=>'product_washer_material'],
			'Cross Over' => ['field'=>'field_cross_over', 'type' => "", 'taxonomy'=>'product_cross_over'],
			'Cross Over 2' => ['field'=>'field_cross_over_2', 'type' => "", 'taxonomy'=>'product_cross_over_2'],
			'Cross Over Sub Sub Cat' => ['field'=>'field_cross_over_sub_sub_cat', 'type' => "", 'taxonomy'=>'product_cross_over_sub_sub_cat'],
			'Name Overide' => ['field'=>'field_name_overide', 'type' => "", 'taxonomy'=>'product_name_overide'],
			'Title Alias' => ['field'=>'field_title_alias', 'type' => "", 'taxonomy'=>''],*/
		]; 
	}

}
