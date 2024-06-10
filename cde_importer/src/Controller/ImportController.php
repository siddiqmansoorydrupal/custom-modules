<?php

namespace Drupal\cde_importer\Controller;



use Drupal\Core\Controller\ControllerBase;

use Drupal\Core\Form\FormBuilder;



class ImportController extends ControllerBase {

  public function product() {
    // Load the custom form.
    $form = \Drupal::formBuilder()->getForm('\Drupal\cde_importer\Form\productimporterForm');
    return $form;
  }
  public function offline() {
    // Load the custom form.
    $form = \Drupal::formBuilder()->getForm('\Drupal\cde_importer\Form\offlineimporterForm');
    return $form;
  }

  public function weekly() {
    // Load the custom form.
    $form = \Drupal::formBuilder()->getForm('\Drupal\cde_importer\Form\weeklyimporterForm');
    return $form;
  }


}

