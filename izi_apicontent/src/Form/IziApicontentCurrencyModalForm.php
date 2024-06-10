<?php
// src/Form/IziApicontentCurrencyModalForm.php

namespace Drupal\izi_apicontent\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class IziApicontentCurrencyModalForm extends FormBase {

  public function getFormId() {
    return 'izi_apicontent_currency_modal_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
	  
	  
	// Load the block plugin.
    $block = \Drupal\block\Entity\Block::load('izi_travel_currencyblock');
    // Render the block content.
    $block_content = \Drupal::entityTypeManager()
      ->getViewBuilder('block')
      ->view($block);
    // Get the renderer service.
    $renderer = \Drupal::service('renderer');
    // Embed the block content into the payment form.
    $form['payment']['currency_block'] = [
      '#markup' => $renderer->render($block_content),
    ];

    return $form;
  }
}
