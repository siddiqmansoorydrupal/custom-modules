<?php

namespace Drupal\izi_webshop_core\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Izi webshop core settings for this site.
 */
class SettingsForm extends ConfigFormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'izi_webshop_core_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['izi_webshop_core.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $form['default_tour_price'] = [
      '#type' => 'number',
      '#step' => '0.01',
      '#required' => true,
      '#title' => $this->t('Default Tour Price'),
      '#default_value' => $this->config('izi_webshop_core.settings')->get('default_tour_price'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    if (!is_numeric($form_state->getValue('default_tour_price'))) {
      $form_state->setErrorByName('default_tour_price', $this->t('The value must be numerical.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config('izi_webshop_core.settings')
      ->set('default_tour_price', $form_state->getValue('default_tour_price'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
