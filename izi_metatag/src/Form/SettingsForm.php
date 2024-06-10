<?php

namespace Drupal\izi_metatag\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure izi_metatag settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_metatag_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['izi_metatag.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['og_site_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site name for og:site_name meta tag'),
      '#default_value' => $this->config('izi_metatag.settings')->get('og_site_name'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];

    $form['izi_metatag_city_all_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for city overview pages without filters'),
      '#description' => $this->t('@city will be replaced with the (translated) name of the city.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_city_all_title'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];
    $form['izi_metatag_city_all_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description for city overview pages without filters'),
      '#description' => $this->t('@city will be replaced with the (translated) name of the city.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_city_all_description'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];

    $form['izi_metatag_city_tour_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for city overview pages with only tours'),
      '#description' => $this->t('@city will be replaced with the (translated) name of the city.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_city_tour_title'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];
    $form['izi_metatag_city_tour_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description for city overview pages with only tours'),
      '#description' => $this->t('@city will be replaced with the (translated) name of the city.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_city_tour_description'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];

    $form['izi_metatag_city_museum_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for city overview pages with only museums'),
      '#description' => $this->t('@city will be replaced with the (translated) name of the city.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_city_museum_title'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];
    $form['izi_metatag_city_museum_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description for city overview pages with only museums'),
      '#description' => $this->t('@city will be replaced with the (translated) name of the city.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_city_museum_description'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];

    $form['izi_metatag_city_quest_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for city overview pages with only quests'),
      '#description' => $this->t('@city will be replaced with the (translated) name of the city.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_city_quest_title'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];
    $form['izi_metatag_city_quest_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description for city overview pages with only quests'),
      '#description' => $this->t('@city will be replaced with the (translated) name of the city.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_city_quest_description'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];

    $form['izi_metatag_country_all_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for country overview pages with all tours'),
      '#description' => $this->t('@country will be replaced with the (translated) name of the country.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_country_all_title'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];
    $form['izi_metatag_country_all_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description for country overview pages with all tours'),
      '#description' => $this->t('@country will be replaced with the (translated) name of the country.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_country_all_description'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];

    $form['izi_metatag_country_tour_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for country overview pages with only tours'),
      '#description' => $this->t('@country will be replaced with the (translated) name of the country.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_country_tour_title'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];
    $form['izi_metatag_country_tour_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description for country overview pages with only tours'),
      '#description' => $this->t('@country will be replaced with the (translated) name of the country.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_country_tour_description'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];

    $form['izi_metatag_country_museum_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for country overview pages with only museums'),
      '#description' => $this->t('@country will be replaced with the (translated) name of the country.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_country_museum_title'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];
    $form['izi_metatag_country_museum_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description for country overview pages with only museums'),
      '#description' => $this->t('@country will be replaced with the (translated) name of the country.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_country_museum_description'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];

    $form['izi_metatag_country_quest_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title for country overview pages with only quests'),
      '#description' => $this->t('@country will be replaced with the (translated) name of the country.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_country_quest_title'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];
    $form['izi_metatag_country_quest_description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description for country overview pages with only quests'),
      '#description' => $this->t('@country will be replaced with the (translated) name of the country.'),
      '#default_value' => $this->config('izi_metatag.settings')->get('izi_metatag_country_quest_description'),
      '#required' => TRUE,
      '#localize' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('izi_metatag.settings')
      ->set('og_site_name', $form_state->getValue('og_site_name'))

      ->set('izi_metatag_city_all_title', $form_state->getValue('izi_metatag_city_all_title'))
      ->set('izi_metatag_city_all_description', $form_state->getValue('izi_metatag_city_all_description'))

      ->set('izi_metatag_city_tour_title', $form_state->getValue('izi_metatag_city_tour_title'))
      ->set('izi_metatag_city_tour_description', $form_state->getValue('izi_metatag_city_tour_description'))

      ->set('izi_metatag_city_museum_title', $form_state->getValue('izi_metatag_city_museum_title'))
      ->set('izi_metatag_city_museum_description', $form_state->getValue('izi_metatag_city_museum_description'))

      ->set('izi_metatag_city_quest_title', $form_state->getValue('izi_metatag_city_quest_title'))
      ->set('izi_metatag_city_quest_description', $form_state->getValue('izi_metatag_city_quest_description'))

      ->set('izi_metatag_country_all_title', $form_state->getValue('izi_metatag_country_all_title'))
      ->set('izi_metatag_country_all_description', $form_state->getValue('izi_metatag_country_all_description'))

      ->set('izi_metatag_country_tour_title', $form_state->getValue('izi_metatag_country_tour_title'))
      ->set('izi_metatag_country_tour_description', $form_state->getValue('izi_metatag_country_tour_description'))

      ->set('izi_metatag_country_museum_title', $form_state->getValue('izi_metatag_country_museum_title'))
      ->set('izi_metatag_country_museum_description', $form_state->getValue('izi_metatag_country_museum_description'))

      ->set('izi_metatag_country_quest_title', $form_state->getValue('izi_metatag_country_quest_title'))
      ->set('izi_metatag_country_quest_description', $form_state->getValue('izi_metatag_country_quest_description'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
