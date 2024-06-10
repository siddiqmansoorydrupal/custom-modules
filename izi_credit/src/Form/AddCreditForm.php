<?php

namespace Drupal\izi_credit\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;

/**
 * Class AddCreditForm.
 */
class AddCreditForm extends FormBase {

  public function getFormId() {
    return 'add_credit_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="add-credit-form-wrapper">';
    $form['#suffix'] = '</div>';

    $form['credits'] = [
      '#type' => 'number',
      '#title' => $this->t('Credits'),
      '#min' => 1,
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Credit'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'add-credit-form-wrapper',
      ],
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Logic to add credits to user's account
    $current_user = \Drupal::currentUser();
    $user = User::load($current_user->id());
    $credits = $form_state->getValue('credits');
    
    // Assuming there's a field 'field_credits' in the user entity to store credits
    $current_credits = $user->get('field_credits')->value;
    $user->set('field_credits', $current_credits + $credits);
    $user->save();

    $this->messenger()->addStatus($this->t('Added @credits credits to your account.', ['@credits' => $credits]));

    // Redirect after submission
    $form_state->setRedirect('izi_credit.credit_list', ['user' => $user->id()]);
  }

  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    return $form;
  }
}
