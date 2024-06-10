<?php

namespace Drupal\cde_custom_address_book\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Custom field handler for rendering operational links as simple links.
 *
 * @ViewsField("views_handler_field_operations")
 */
class CustomOperationalLinksFieldHandler extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $output = '';
    $operations = $this->get_value($values);

    foreach ($operations as $key => $operation) {
      // Generate the link markup.
      $link_markup = '<a href="' . $operation['url'] . '">' . $operation['title'] . '</a>';
      
      // Append the link markup to the output.
      $output .= $link_markup . ' ';
    }

    return $output;
  }
}
