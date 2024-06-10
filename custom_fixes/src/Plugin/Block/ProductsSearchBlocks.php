<?php

namespace Drupal\custom_fixes\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Plugin\PluginBase;

/**
 * Provides a 'Products Search Facet' Block.
 *
 * @Block(
 *   id = "products_search_blocks",
 *   admin_label = @Translation("Products Search Blocks"),
 *   category = @Translation("Products Search Blocks"),
 * )
 *
 * @todo DELETE this.
 */
class ProductsSearchBlocks extends BlockBase {

 /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    $config = \Drupal::config('custom_fixes.settings');
    $search_facets_listing = $config->get('search_facets_listing');

    $facets_listing = preg_split('/\r\n|\r|\n/', $search_facets_listing);

    $filters = [
        'product_category' => 'field_product_category_taxonomy',
        'metric_standard' => 'field_metric_standard',
        'metric_specification' => 'field_metric_specification',
        'inner_diameter' => 'field_inner_diameter',
        'diameter' => 'field_diameter',
        'thread_pitch' => 'field_thread_pitch',
        'length' => 'field_lenght',
        'shaft_diameter' => 'field_shaft_diameter',
        'outer_diameter' => 'field_outer_diameter',
        'washer_outer_diameter' => 'field_washer_ourter_diameter',
        'shank' => 'field_shank',
        'point' => 'field_point',
        'hex_washer' => 'field_hex_washer',
        'head_height' => 'field_head_height',
        'distance_across_flats' => 'field_disatnce_across_flats',
        'tek_point' => 'field_tek_point',
        'grip_range' => 'field_grip_range',
        'thickness_term' => 'field_thickness',
        'tab_base' => 'field_tab_base',
        'base_diameter' => 'field_base_diameter',
        'sub_sub_cat' => 'field_sub_sub_cat',
        'type' => 'field_type',
        'head_style' => 'field_head_style',
        'abnormal_head_diameter' => 'field_abnormal_head_diameter',
        'drive' => 'field_drive',
        'style' => 'field_style',
        'point_type' => 'field_point_type',
        'washer_type' => 'field_washer_type',
        'thread_length' => 'field_thread_lenght',
        'thread_type' => 'field_thread_type',
        'weld_style' => 'field_weld_stlye',
        'military_specification' => 'field_military_specification',
        'national_aerospace_standards' => 'field_national_aerospace_standard',
        'wings' => 'field_wings',
        'material' => 'field_material',
        'finish' => 'field_finish',
        'internal_expander' => 'field_internal_expander',
        'external_sleeve' => 'field_external_sleeve',
        'body_material' => 'field_body_material',
        'pin_material' => 'field_pin_material',
        'screw_material' => 'field_screw_material',
        'washer_material' => 'field_washer_material',
    ];

    foreach ($facets_listing as $key => $facet_str) {
        $getFactField = explode('|', $facet_str);
        $facetFeild = $getFactField[1];
        $facetBlockKey = array_search($facetFeild, $filters);
        if ($facetBlockKey) {
            $facets_blocks[$facetBlockKey] = $getFactField[0];
        }
    }

    foreach ($facets_blocks as $block_machine_name => $block_label) {
        $facet = $block_machine_name;
        $render = [];
        $block_manager = \Drupal::service('plugin.manager.block');
        $config = [];
        $block_plugin = $block_manager->createInstance('facet_block' . PluginBase::DERIVATIVE_SEPARATOR . $facet, $config);
        if ($block_plugin) {
            $access_result = $block_plugin->access(\Drupal::currentUser());
            if ($access_result) {
                $render = $block_plugin->build();
            }
        }
        if (count($render) > 0) {
            $render_str = \Drupal::service('renderer')->renderPlain($render);
            if (!empty($render_str)) {
                if ($render_str->__toString() != '') {
                    $blocks_arry[] = '<div class="block-facets-ajax block-facet"><h3>' . $block_label . '</h3>' . $render_str->__toString() .'</div>';
                }
            }
        }
    }
    // dump($blocks_arry);exit;
    $markup_str = implode('',$blocks_arry);
    $markup = Markup::create($markup_str);

    return [
      '#markup' => "",
    ];
  }

}
