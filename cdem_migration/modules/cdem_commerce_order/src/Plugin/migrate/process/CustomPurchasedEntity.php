<?php

namespace Drupal\cdem_commerce_order\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * @MigrateProcessPlugin(
 *   id = "custom_purchased_entity"
 * )
 */
class CustomPurchasedEntity extends ProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // return strrev($value);
    $product_id['target_id'] = 0;
    $product_sku = $row->getSourceProperty('line_item_label');
    $query = \Drupal::entityQuery('commerce_product');
    $query->condition('field_supplier_sku', $product_sku);
    $query->condition('status', 1);
    $product_ids = $query->accessCheck(FALSE)->execute();
    if (!empty($product_ids)) {
        $product_id['target_id'] = reset($product_ids);
    }
    
    return $product_id;
  }
}