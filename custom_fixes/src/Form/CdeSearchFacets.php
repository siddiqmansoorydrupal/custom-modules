<?php

namespace Drupal\custom_fixes\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\block\Entity\Block;

/**
 * Configure example settings for this site.
 */
class CdeSearchFacets extends ConfigFormBase {

  /** 
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'custom_fixes.settings';

  /** 
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cde_facet_search_settings';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /** 
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['search_facets_listing'] = [
        '#type' => 'textarea',
        '#title' => t('Search facets listing'),
        '#default_value' => $config->get('search_facets_listing'),
        '#required' => TRUE,
        '#rows' => 30,
        '#cols' => 60,
    ];

    return parent::buildForm($form, $form_state);
  }

  /** 
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Advanced facets
    $ad_filters = [
      'product_category_advance' => 'field_product_category_taxonomy',
      'metricstandardadvancedsearch' => 'field_metric_standard',
      'metricspecificationadvancedsearch' => 'field_metric_specification',
      'innerdiameteradvancedsearch' => 'field_inner_diameter',
      'diameteradvancedsearch' => 'field_diameter',
      'threadpitch_2' => 'field_thread_pitch',
      'lengthadvancedsearch' => 'field_lenght',
      'shaftdiameteradvancedsearch' => 'field_shaft_diameter',
      'outerdiameteradvancedsearch' => 'field_outer_diameter',
      'washerouterdiameteradvancedsearch' => 'field_washer_ourter_diameter',
      'shankadvancedsearch' => 'field_shank',
      'pointadvancedsearch' => 'field_point',
      'hexwasheradvancedsearch' => 'field_hex_washer',
      'headheightadvancedsearch' => 'field_head_height',
      'distance_across_flats_advanced_search' => 'field_disatnce_across_flats',
      'tekpointadvancedsearch' => 'field_tek_point',
      'grip_range_advanced' => 'field_grip_range',
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
      'threadpitchadvancedsearch_2' => 'field_thread_type',
      'weldstyleadvancedsearch' => 'field_weld_stlye',
      'militaryspecificationadvancedsearch' => 'field_military_specification',
      'national_aero_space_standards_advanced' => 'field_national_aerospace_standard',
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

  // Normal facets
  $filters = [
    'product_category' => 'field_product_category_taxonomy',
    'metricstandard' => 'field_metric_standard',
    'metricspecification' => 'field_metric_specification',
    'innerdiameter' => 'field_inner_diameter',
    'diameter' => 'field_diameter',
    'threadpitch_4' => 'field_thread_pitch',
    'length' => 'field_lenght',
    'shaftdiameter' => 'field_shaft_diameter',
    'outerdiameter' => 'field_outer_diameter',
    'washerouterdiameter' => 'field_washer_ourter_diameter',
    'shank' => 'field_shank',
    'point' => 'field_point',
    'hexwasher' => 'field_hex_washer',
    'headheight' => 'field_head_height',
    'distanceacrossflats' => 'field_disatnce_across_flats',
    'tekpoint' => 'field_tek_point',
    'grip_range' => 'field_grip_range',
    'thickness_2' => 'field_thickness',
    'tabbase' => 'field_tab_base',
    'basediameter_2' => 'field_base_diameter',
    'subsubcat' => 'field_sub_sub_cat',
    'type' => 'field_type',
    'headstyle' => 'field_head_style',
    'abnormalheaddiameter' => 'field_abnormal_head_diameter',
    'drive' => 'field_drive',
    'style' => 'field_style',
    'pointtype' => 'field_point_type',
    'washertype' => 'field_washer_type',
    'threadlength' => 'field_thread_lenght',
    'threadtype' => 'field_thread_type',
    'weldstyle' => 'field_weld_stlye',
    'militaryspecification' => 'field_military_specification',
    'national_aero_space_standards' => 'field_national_aerospace_standard',
    'wings' => 'field_wings',
    'material' => 'field_material',
    'finish' => 'field_finish',
    'internalexpander' => 'field_internal_expander',
    'externalsleeve' => 'field_external_sleeve',
    'bodymaterial' => 'field_body_material',
    'pinmaterial' => 'field_pin_material',
    'screwmaterial' => 'field_screw_material',
    'washermaterial' => 'field_washer_material',
];

  $listing = $form_state->getValue('search_facets_listing');
  $facets_listing = preg_split('/\r\n|\r|\n/', $listing);

  foreach ($facets_listing as $weight_key => $facets_listing_value) {
    $getFactField = explode('|', $facets_listing_value);
    $facetFeild = $getFactField[1];
    $ad_facetBlockKey = array_search($facetFeild, $ad_filters);
    if ($ad_facetBlockKey) {
        $ad_block = Block::load($ad_facetBlockKey);
        if (!is_null($ad_block)) {
          $ad_block->setWeight($weight_key);
          $ad_blcok_settings = $ad_block->get('settings');
          $ad_blcok_settings['label'] = $getFactField[0];
          $ad_block->set('settings', $ad_blcok_settings);
          $ad_block->save();
        }
    }

    $facetBlockKey = array_search($facetFeild, $filters);
    if ($facetBlockKey) {
        $block = Block::load($facetBlockKey);
        if (!is_null($block)) {
          $block->setWeight($weight_key);
          $blcok_settings = $block->get('settings');
          $blcok_settings['label'] = $getFactField[0];
          $block->set('settings', $blcok_settings);
          $block->save();
        }
    }
  }

    // Retrieve the configuration.
    $this->config(static::SETTINGS)
      // Set the submitted configuration setting.
      ->set('search_facets_listing', $form_state->getValue('search_facets_listing'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}