<?php

// File: src/Controller/ModalFormController.php
namespace Drupal\izi_apicontent\Controller;

use Drupal\Core\Controller\ControllerBase;

class ModalFormController extends ControllerBase {

  /**
   * Controller content callback: Display modal form.
   */
  public function content() {
    // Load the modal form.
    $modal_form = \Drupal::formBuilder()->getForm('\Drupal\izi_apicontent\Form\StripePaymentForm');

    // Return modal form content.
    return [
      '#type' => 'inline_template',
      '#template' => '<div id="modal-form">{{ modal_form }}</div>',
      '#context' => [
        'modal_form' => $modal_form,
      ],
    ];
  }
}
