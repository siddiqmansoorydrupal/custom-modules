<?php

namespace Drupal\cdem_nodes\Plugin\migrate\process;

use Drupal\commerce_price\Calculator;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Scales the price from Commerce 1 to Commerce 2.
 *
 * This plugin is put in the pipeline by field migration for fields of type
 * 'commerce_price'. It is also used in the product variation migration and
 * the order item migration.
 *
 * The commerce_price process plugin is put in the pipeline by field migrations
 * for fields of type 'commerce_price'. It is also used in the product variation
 * migration and the order item migration.
 *
 * This plugin is used to convert the Commerce 1 price array to a Commerce 2
 * price array. The source value is an  associative  array with keys, 'amount',
 * 'currency_code' and 'fraction_digits'.
 *
 * Input array::
 * - amount: The price number
 * - currency_code: The currency code.
 * - fraction_digits: The number of fraction digits for this given currency.
 *
 * Returned array::
 * - number: The converted price number
 * - currency_code: The currency code.
 *
 * An empty array is returned for all errors.
 *
 * @code
 * plugin: commerce1_migrate_commerce_price
 * source: commerce1_price_array
 * @endcode
 *
 * When input the input is
 *  [
 *    'amount' => '123',
 *    'currency_code' => 'NZD',
 *    'fraction_digits' => 3,
 * ];
 * The output is
 *  [
 *    'number' => '0.123',
 *    'currency_code' => 'NZD',
 * ];
 *
 * @MigrateProcessPlugin(
 *   id = "upgrade_migrate_supplier_price"
 * )
 */
class SupplierPrice extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $new_value = [];
    if (isset($value['amount']) && isset($value['currency_code'])) {
      $new_value = [
        'number' => Calculator::divide($value['amount'], bcpow(10, 2)),
        'currency_code' => $value['currency_code'],
      ];
    }

    return $new_value;
  }

}
