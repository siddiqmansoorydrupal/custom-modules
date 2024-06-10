<?php

namespace Drupal\izi_credit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\user\Entity\User;

/**
 * Class CreditController.
 */
class CreditController extends ControllerBase {

  /**
   * Lists credits for a user.
   */
  public function listCredits($user) {
    // Fetch and render a list of credits and balance for the user.
    // Replace the following dummy data with actual fetching logic
    $credits = [
      ['date' => '2023-01-01', 'amount' => 100, 'type' => 'credit'],
      ['date' => '2023-02-01', 'amount' => -50, 'type' => 'debit'],
    ];
    $balance = 50;  // Fetch the actual balance from user entity or custom logic

    $items = array_map(function($credit) {
      return "{$credit['date']} - {$credit['amount']} ({$credit['type']})";
    }, $credits);

    $build = [
      '#markup' => '<div><strong>Available Balance:</strong> ' . $balance . ' credits</div>',
    ];

    $build['add_credit_button'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Credit'),
      '#url' => \Drupal\Core\Url::fromRoute('izi_credit.add_credit', ['user' => $user]),
      '#attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
      ],
    ];

    $build['credit_list'] = [
      '#theme' => 'item_list',
      '#items' => $items,
      '#title' => $this->t('Credit List for User %user', ['%user' => $user]),
    ];

    return $build;
  }

  /**
   * Adds credits to the user's account.
   */
  public function addCredit(Request $request) {
    // Ensure you implement the form submission and processing logic.
    $form = \Drupal::formBuilder()->getForm('Drupal\izi_credit\Form\AddCreditForm');

    // Return the form render array
    return $form;
  }
}
