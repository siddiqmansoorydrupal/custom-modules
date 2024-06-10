<?php

namespace Drupal\custom_fixes\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformSubmissionForm;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\user\Entity\User;
use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;

/**
 * NotifyCustomer class.
 */
class NotifyCustomer extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'notify_customer_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL)
  {

    $uid  = $options['uid'];
	$user = User::load($uid);
	$account_name = '';

	if($user){
		$profile_id = \Drupal::entityTypeManager()
			->getStorage('profile')
			->loadByProperties([
				'uid' => $uid,
				'type' => 'customer',
				'is_default' => '1',
			  ]);
		if ($profile_id) {
			$address_values = array_values($profile_id)[0]->get('address')->getValue()[0];
			$account_name = $address_values['given_name'] . ' ' . $address_values['family_name'];
		} else {
			$user = \Drupal\user\Entity\User::load($arg[2]);
			$account_name = $user->name->value;
		}
	}

    $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');
    /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */
    $offline_item_request_update_email = $email_storage->load('notify_customer_to_offline_product');

    $email_body = '';
    if (!empty($offline_item_request_update_email->getBody())) {
      $email_body = $offline_item_request_update_email->getBody();
    }

	$site_url = \Drupal::request()->getBaseUrl();
	$product_list = $site_url . '/user/' . $user->id() . '/parts-list';

	$email_body = str_replace("[account_name]", $account_name, $email_body);
	$email_body = str_replace("[Account_name]", $account_name, $email_body);
	$email_body = str_replace("[product_list]", $product_list, $email_body);


    $form['#prefix'] = '<div style="max-height:300px;" id="request_update_modal_form"><h2>Quote List Email Notification to Customer</h2>';
    $form['#suffix'] = '</div>';

    $form['notes'] = [
      '#title' => $this->t('Notes/Comments'),
      '#type' => 'text_format',
      '#format'=> 'full_html',
      '#default_value' => $email_body,
    ];

    $form['uid'] = [
      '#type' => 'hidden',
      '#default_value' => $uid,
    ];


    $form['actions'] = array('#type' => 'actions');
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state)
  {
    $response = new AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      $form_values = $form_state->getValues();

      $uid = $form_values['uid'];
      $email_desc = $form_values['notes']['value'];
     //\Drupal::logger('order_id')->notice('<pre><code>' . print_r($form_values, TRUE) . '</code></pre>' );

      //dump($form_values); exit;
        $user = User::load($uid);
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'cde_order';
        $key = 'offline_item_notify_user';
        //$to = $user->get('mail')->getString();
        $to = $user->get('mail')->getString();
        $account_name = $user->get('name')->getString();
        $site_url = \Drupal::request()->getBaseUrl();
        $product_list = $site_url . '/user/' . $user->id() . '/parts-list';
        $email_body = '';
        if (!empty($email_desc)) {
          $email_body = $email_desc;
        }
        $offline_item_body = $email_body;

        $host = \Drupal::request()->getSchemeAndHttpHost();

        $product_list_body = str_replace("[product_list]", $product_list, $offline_item_body);
        $account_name_body = str_replace("[account_name]", $account_name, $product_list_body);
        $site_url_body = str_replace("[site:url]", $host, $account_name_body);
        $params['message'] = $site_url_body;
        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $send = true;

        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        if ($result['result'] !== true) {
          \Drupal::messenger()->addMessage('There was a problem sending your message and it was not sent.');
        } else {
         $mailManager->mail($module, $key, \Drupal::config('system.site')->get('mail'), $langcode, $params, NULL, $send);
          \Drupal::messenger()->addMessage('Email request has been sent.');
        }

      //$currentURL = Url::fromRoute('<current>');
      $response->addCommand(new RedirectCommand($product_list));
      /*$response->addCommand(new CloseModalDialogCommand());
      $response->addCommand(new OpenModalDialogCommand("Success!", 'The modal form has been submitted.', ['width' => 800]));*/
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {

  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames()
  {
    return ['config.modal_form_example_modal_form'];
  }

}
