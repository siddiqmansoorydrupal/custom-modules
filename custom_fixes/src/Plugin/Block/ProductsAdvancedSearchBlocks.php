<?php

namespace Drupal\custom_fixes\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Render\Markup;

/**
 * Provides a 'Products Advanced Search Facet' Block.
 *
 * @Block(
 *   id = "products__advanced_search_blocks",
 *   admin_label = @Translation("Products Advanced Search Blocks"),
 *   category = @Translation("Products Advanced Search Blocks"),
 * )
 *
 * @todo DELETE this.
 */
class ProductsAdvancedSearchBlocks extends BlockBase {

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
        'productcategoryadvancedsearch' => 'field_product_category_taxonomy',
        'metricstandardadvancedsearch' => 'field_metric_standard',
        'metricspecificationadvancedsearch' => 'field_metric_specification',
        'innerdiameteradvancedsearch' => 'field_inner_diameter',
        'diameteradvancedsearch' => 'field_diameter',
        'threadpitchadvancedsearch' => 'field_thread_pitch',
        'lengthadvancedsearch' => 'field_lenght',
        'shaftdiameteradvancedsearch' => 'field_shaft_diameter',
        'outerdiameteradvancedsearch' => 'field_outer_diameter',
        'washerouterdiameteradvancedsearch' => 'field_washer_ourter_diameter',
        'shankadvancedsearch' => 'field_shank',
        'pointadvancedsearch' => 'field_point',
        'hexwasheradvancedsearch' => 'field_hex_washer',
        'headheightadvancedsearch' => 'field_head_height',
        'distanceacrossflatsadvancedsearch' => 'field_disatnce_across_flats',
        'tekpointadvancedsearch' => 'field_tek_point',
        'griprangeadvancedsearch' => 'field_grip_range',
        'thicknessadvancedsearch' => 'field_thickness',
        'tabbaseadvancedsearch' => 'field_tab_base',
        'basediameteradvancedsearch' => 'field_base_diameter',
        'subcategoryadvancedsearch' => 'field_sub_sub_cat',
        'typeadvancedsearch' => 'field_type',
        'headstyleadvancedsearch' => 'field_head_style',
        'abnormalheaddiameteradvancedsearch' => 'field_abnormal_head_diameter',
        'driveadvancedsearch' => 'field_drive',
        'styleadvancedsearch' => 'field_style',
        'pointtypeadvancedsearch' => 'field_point_type',
        'washertypeadvancedsearch' => 'field_washer_type',
        'threadlengthadvanced' => 'field_thread_lenght',
        'threadtypeadvancedsearch' => 'field_thread_type',
        'weldstyleadvancedsearch' => 'field_weld_stlye',
        'militaryspecificationadvancedsearch' => 'field_military_specification',
        'nationalaerospacestandardsadvancedsearch' => 'field_national_aerospace_standard',
        'wingsadvancedsearch' => 'field_wings',
        'materialadvancedsearch' => 'field_material',
        'finishadvancedsearch' => 'field_finish',
        'internalexpanderadvancedsearch' => 'field_internal_expander',
        'externalsleeveadvancedsearch' => 'field_external_sleeve',
        'bodymaterialadvancedsearch' => 'field_body_material',
        'pinmaterialadvancedsearch' => 'field_pin_material',
        'screwmaterialadvancedsearch' => 'field_screw_material',
        'washermaterialadvancedsearch' => 'field_washer_material',
    ];

    foreach ($facets_listing as $key => $facet_str) {
        $getFactField = explode('|', $facet_str);
        $facetFeild = $getFactField[1];
        $facetBlockKey = array_search($facetFeild, $filters);
        if ($facetBlockKey) {
            $facets_blocks[] = $facetBlockKey;
        }
    }

    foreach ($facets_blocks as $key => $block_machine_name) {
        $block_str = [];
        $block_str = explode('|', $block_machine_name);
        $block_id = $block_str[0];
        $block = \Drupal::entityTypeManager()
            ->getStorage('block')
            ->load($block_id);
        if (!empty($block)) {
            $block_content = \Drupal::entityTypeManager()
            ->getViewBuilder('block')
            ->view($block);
            $pre_render = $block_content;
        }

        if (!empty($pre_render)) {
            $render_str = \Drupal::service('renderer')->renderPlain($pre_render);
            if (!empty($render_str)) {
                if ($render_str->__toString() != '') {
                    $blocks_arry[] = $render_str->__toString();
                }
            }
        }

    }

    $markup_str = implode('',$blocks_arry);
    $markup = Markup::create($markup_str);

    return [
      '#markup' => "",
    ];
  }

}
