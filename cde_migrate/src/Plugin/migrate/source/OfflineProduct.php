<?php
/**
 * @file
 * Contains \Drupal\cde_migrate\Plugin\migrate\source\Product.
 */
 
namespace Drupal\cde_migrate\Plugin\migrate\source;
 
/*use Drupal\migrate\Plugin\SourceEntityInterface;*/
use Drupal\migrate\Row;
use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate_drupal\Plugin\migrate\source\DrupalSqlBase;
/**
 * Extract users from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "cde_migrate_offlineproduct"
 * )
 */
class OfflineProduct extends DrupalSqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Select node in its last revision.
    $query = $this->select('commerce_product', 'p')
      ->condition('p.type', 'kanebridge_products', '=')
      ->fields('p', array(
        'product_id',
        'revision_id',
        'sku',
        'title',
        'type',
        'language',
        'uid',
        'status',
        'created',
        'changed',
        'data',
      ));
	  $query->leftJoin('field_data_field_offline_item', 'alias', 'alias.entity_id = p.product_id');
	  $query->condition('alias.field_offline_item_value', '1', '=');
      /*$query->range(0, 20000);*/
	return $query;

  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = $this->baseFields();
	
	$fields = ['field_5price', 'field_abnormal_head_diameter', 'field_base_diameter', 'field_blue_devil_item_no', 'field_blue_devil_price', 'field_body_material', 'field_category_taxonomy', 'field_cross_over', 'field_cross_over_2', 'field_cross_over_sub_sub_cat', 'field_date_added', 'field_description', 'field_description_long_text', 'field_diameter', 'field_distance_across_flats', 'field_domestic_import', 'field_drive', 'field_eligible_customers', 'field_expiration_date', 'field_external_sleeve', 'feeds_item', 'field_finish', 'field_global_sort', 'field_grip_range', 'field_grip_range_taxonomy', 'field_head_height', 'field_head_style', 'field_hex_washer', 'field_image_commerce_product', 'field_inner_diameter', 'field_in_stock', 'field_internal_expander', 'field_internal_notes', 'field_item_description', 'field_length', 'field_live_item', 'field_long_description', 'field_material', 'field_metric_standard', 'field_metric_standard_', 'field_military_specification', 'field_name_overide', 'field_national_aero_space', 'field_national_aerospace_standar', 'field_offline_item', 'field_outer_diameter', 'field_part_cross_reference', 'field_pieces_per_lb', 'field_pin_material', 'field_point', 'field_point_type', 'commerce_price', 'field_price_break_1', 'field_price_break_3', 'field_product_category_taxonomy', 'field_product_code', 'field_product_field', 'field_product_size_taxonomy', 'field_product_sku', 'field_qty_break_1', 'field_quantity_per_box', 'field_screw_material', 'field_shaft_diameter', 'field_shank', 'field_solr_text', 'field_sort_size', 'field_sort_sizes', 'field_offline_item_status', 'field_style', 'field_sub_sub_cat', 'field_supplier_2', 'field_supplier_reference', 'field_supplier_2_cost', 'field_supplier_cost', 'field_supplier_2_sku', 'field_tab_base', 'field_tek_point', 'field_tele_part', 'field_thickness', 'field_thickness_taxonomy', 'field_thread_length', 'field_thread_pitch', 'field_thread_type', 'field_field_title_alias_product', 'field_title_alias', 'field_type', 'field_unit_of_measure', 'field_washer_material', 'field_washer_outer_diameter', 'field_washer_type', 'field_weight', 'field_weight_per_um', 'field_weld_style', 'field_wings', 'field_product_category', 'field_product_size', 'field_product_subcategory'];
	
	foreach($fields as $field):
		$fields[$field] = $this->t('Value of '.$field);
	endforeach;
	 
    return $fields;
  }
  
  /**
   * {@inheritdoc}
   */
  public function getResultsproduct_id($table, $product_id, $fields, $type = NULL) {

    if ($type == "array") {
      $return = [];
    }
    else {
      $return = '';
    }

    $result = $this->getDatabase()->query('SELECT flo.' . $fields . ' as return_val FROM {' . $table . '} flo WHERE flo.product_id = :product_id ', [':product_id' => $product_id]);
    foreach ($result as $record) {

      if ($type == "date") {
        $return = date("Y-m-d", strtotime($record->return_val));
      }
      elseif ($type == "array") {
        $return[] = $record->return_val;
      }
      else {
        $return = $record->return_val;
      }

    }

    return $return;

  }
  
  /**
   * {@inheritdoc}
   */
  public function getResults($table, $product_id, $fields, $type = NULL) {

    if ($type == "array") {
      $return = [];
    }
    else {
      $return = '';
    }

    $result = $this->getDatabase()->query('SELECT flo.' . $fields . ' as return_val FROM {' . $table . '} flo WHERE flo.entity_id = :product_id ', [':product_id' => $product_id]);
    foreach ($result as $record) {

      if ($type == "date") {
        $return = date("Y-m-d", strtotime($record->return_val));
      }
      elseif ($type == "array") {
        $return[] = $record->return_val;
      }
      else {
        $return = $record->return_val;
      }

    }

    return $return;

  }

  /**
   * {@inheritdoc}
   */
  public function getTaxonomyResults($table, $product_id, $fields, $type = NULL) {

    $return = [];

    $result = $this->getDatabase()->query('
	  SELECT
		taxonomy_vocabulary.machine_name, taxonomy_term_data.name,flo.' . $fields . ' as return_val
	  FROM
		{' . $table . '} flo
	  INNER JOIN `taxonomy_term_data` ON taxonomy_term_data.tid=flo.' . $fields . '	
	  INNER JOIN `taxonomy_vocabulary` ON taxonomy_vocabulary.vid=taxonomy_term_data.vid	
	  WHERE
        flo.entity_id = :product_id 
    ', [':product_id' => $product_id]);
    foreach ($result as $record) {

      $field_tags_tid = $record->return_val;
	  
	  $term = \Drupal::entityQuery('taxonomy_term')
        ->condition('name', $record->name)
        ->condition('vid', $record->machine_name)
        ->accessCheck(false)
        ->execute(); 
      

      foreach ($term as $_term) {
        $return[] = $_term;
      }
    }

    return $return;

  }
  
  /**
   * {@inheritdoc}
   */
  public function getEntityResults($table, $nid, $fields, $type = NULL) {

    $return = [];

    $result = $this->getDatabase()->query('
	  SELECT
		node.title,flo.' . $fields . ' as return_val
	  FROM
		{' . $table . '} flo
	  INNER JOIN `node` ON node.nid=flo.' . $fields . '	
	  WHERE
        flo.entity_id = :nid 
    ', [':nid' => $nid]);
    foreach ($result as $record) {

      $field_tags_tid = $record->return_val;

      $term = \Drupal::entityQuery('node')
        ->condition('title', $record->title)
        ->accessCheck(false)
        ->execute();

      foreach ($term as $_term) {
        $return[] = $_term;
      }
    }

    return $return;

  }

  /**
   * {@inheritdoc}
   */
  public function getFileResults($table, $product_id, $fields, $bundle = NULL) {

    $return = [];

    $result = $this->getDatabase()->query('
		SELECT flo.' . $fields . ' as return_val, file_managed.filename, file_managed.uri 
		FROM {' . $table . '} flo 
		LEFT JOIN {file_managed} file_managed ON file_managed.fid=flo.' . $fields . ' 
		WHERE flo.entity_id = :product_id AND flo.bundle = :bundle ', [
    ':product_id' => $product_id,
    ':bundle' => $bundle,
  ]
    );

    foreach ($result as $record) {

      $filename = $record->filename;
      $filepath = $record->uri;

      /*$filepath = str_replace("public://", "public://old_files/", $filepath);*/
      $filepath = str_replace("public://", "https://cdefasteners.com/sites/default/files/", $filepath);
      if (str_contains($filepath, 'private://')) {
        
      }

      $file_temp = file_get_contents($filepath);
      $file_file_save_data = file_save_data($file_temp, 'public://' . $filename, FileSystemInterface::EXISTS_RENAME);
      $_file_save_data = File::load($file_file_save_data->id());

      if ($_file_save_data) {
        $return[] = $_file_save_data->id();
      }
    }
    return $return;

  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $product_id = $row->getSourceProperty('product_id');
    $sku = $row->getSourceProperty('sku');
    $title = $row->getSourceProperty('title');
	$row->setSourceProperty('sku', $sku);
	
	$fields_users = ['field_eligible_customers'];
	
	foreach($fields_users as $field):
		/*$row->setSourceProperty("field_data_".$field, $product_id, $field.'_target_id',"array");*/		
		$row->setSourceProperty("field_data_".$field, $this->getResults("field_data_".$field, $product_id, $field.'_target_id',"array"));	
	endforeach;
	
	$row->setSourceProperty("field_in_stock", "O");
	
	$fields_date = ['field_date_added','field_expiration_date'];
	
	foreach($fields_date as $field):
		$row->setSourceProperty($field, $this->getResults("field_data_".$field, $product_id, $field.'_value',"date"));
	endforeach;
	
		
	if(empty($row->getSourceProperty('field_date_added'))){
		$row->setSourceProperty('field_date_added', date("Y-m-d", $row->getSourceProperty('created')));
	}
	/*print_r($row);/*die;/**/

    return parent::prepareRow($row);
  }
  

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['product_id']['type'] = 'integer';
    $ids['product_id']['alias'] = 'n';
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function bundleMigrationRequired() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function entityTypeId() {
    return 'node';
  }

  /**
   * Returns the user base fields to be migrated.
   *
   * @return array
   *   Associative array having field name as key and description as value.
   */
  protected function baseFields() {
    $fields = array(
      'product_id' => $this->t('Node ID'),
      'vid' => $this->t('Version ID'),
      'type' => $this->t('Type'),
      'title' => $this->t('Title'),
      'format' => $this->t('Format'),
      'teaser' => $this->t('Teaser'),
      'uid' => $this->t('Authored by (uid)'),
      'created' => $this->t('Created timestamp'),
      'changed' => $this->t('Modified timestamp'),
      'status' => $this->t('Published'),
      'promote' => $this->t('Promoted to front page'),
      'sticky' => $this->t('Sticky at top of lists'),
      'uuid' => $this->t('Universally Unique ID'),
      'language' => $this->t('Language (fr, en, ...)'),
    );
    return $fields;
  }

}

