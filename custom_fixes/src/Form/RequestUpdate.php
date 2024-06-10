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
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * RequestUpdate class.
 */
class RequestUpdate extends FormBase
{

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'request_update_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL)
  {

    $product_id = $options['product_id'];

	$arg = explode('_', $options['product_id']);

	$product_id = $arg[0];
	$_uid = $arg[1];


    $product = \Drupal\commerce_product\Entity\Product::load($product_id);
    //\Drupal::logger('text')->info('<pre><code>' . print_r($product->get('field_category_taxonomy')->first()->getValue()['target_id'], true) . '</code></pre>');
    //dump($product);
    if ($product->field_image_commerce_product->entity) {
      $image_uri = $product->field_image_commerce_product->entity->getFileUri();
      if ($image_uri) {
        $imagepath = ImageStyle::load('thumbnail')->buildUrl($image_uri);
      } elseif ($product->field_category_taxonomy->entity) {
        $field_category_taxonomy = $product->get('field_category_taxonomy')->first()->getValue()['target_id'];

        $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($field_category_taxonomy);
        $image_uri = $term_obj->field_product_image->entity->getFileUri();
        if ($image_uri) {
          $imagepath = ImageStyle::load('thumbnail')->buildUrl($image_uri);
        } else {
          $imagepath = '/themes/custom/cde_subtheme/img/image-preview-icon-picture-placeholder-vector-31284806.jpg';
        }
      } else {
        $imagepath = '/themes/custom/cde_subtheme/img/image-preview-icon-picture-placeholder-vector-31284806.jpg';
      }

    } elseif ($product->field_category_taxonomy->entity) {
      $field_category_taxonomy = $product->get('field_category_taxonomy')->first()->getValue()['target_id'];

      $term_obj = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($field_category_taxonomy);
	  if($term_obj->field_product_image->entity){
		$image_uri = $term_obj->field_product_image->entity->getFileUri();
		  if ($image_uri) {
			$imagepath = ImageStyle::load('thumbnail')->buildUrl($image_uri);
		  } else {
			$imagepath = '/themes/custom/cde_subtheme/img/image-preview-icon-picture-placeholder-vector-31284806.jpg';
		  }
	  }else{
		  $imagepath = '/themes/custom/cde_subtheme/img/image-preview-icon-picture-placeholder-vector-31284806.jpg';
	  }

    } else {
      $imagepath = '/themes/custom/cde_subtheme/img/image-preview-icon-picture-placeholder-vector-31284806.jpg';
    }
    $supplier_sku = $product->field_supplier_2_sku->value;
    $supplier_name = '';
    if ($product->hasField('field_supplier_reference')) {
      //$suppliers = $product->get('field_supplier_reference')->referencedEntities();
      if ($product->field_supplier_reference->entity) {
        $entity_id = $product->get('field_supplier_reference')->first()->getValue()['target_id'];
        $supplier_obj = \Drupal::entityTypeManager()->getStorage('node')->load($entity_id);
        $supplier_name = $supplier_obj->title->value;
      }
    }



    //$omage = $product->field_image->value;
    // $original_image = $product->field_image->entity->getFileUri();

    $form['#prefix'] = '<div id="request_update_modal_form"><h2>Request Update - Expired Items</h2><p class="product-description"><img src="' . $imagepath . '"><strong>Product:</strong> ' . $product->getTitle() . '<br><strong>Box Count:</strong> ' . number_format($product->get('field_quantity_per_box')->getString(), 0) . '<br>&nbsp;</p>';
    $form['#suffix'] = '</div>';

    for ($i = 1; $i < 100; $i++) {
      $boxes_options[$i] = $i;
    }

    $form['number_of_boxes'] = [
      '#type' => 'select',
      '#title' => $this->t('How many boxes would you like?'),
      '#required' => TRUE,
      '#options' => $boxes_options,
      '#default_value' => 1,
      '#empty_value' => '_none',
      '#empty_option' => '- None -',
    ];

    $form['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes/Comments'),
    ];

    $form['product_id'] = [
      '#type' => 'hidden',
      '#default_value' => $product_id,
    ];
    $form['supplier_name'] = [
      '#type' => 'hidden',
      '#default_value' => $supplier_name,
    ];
    $form['supplier_sku'] = [
      '#type' => 'hidden',
      '#default_value' => $supplier_sku,
    ];

	$form['uid'] = [
		'#type' => 'hidden',
		'#default_value' => $_uid,
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
      $product = Product::load($form_values['product_id']);
      $product->set('field_offline_item_status', "pending_update");
      $product->save();
      // Get submission values and data.
      $values = [
        'webform_id' => 'request_update_expired_items',
        'entity_type' => NULL,
        'entity_id' => NULL,
        'in_draft' => FALSE,
        'uid' => $form_values['uid'],
        // 'langcode' => 'en',
        // 'token' => 'pgmJREX2l4geg2RGFp0p78Qdfm1ksLxe6IlZ-mN9GZI',
        // 'uri' => '/webform/my_webform/api',
        // 'remote_addr' => '',
        'data' => [
          'product_id' => $form_values['product_id'],
          'product_title' => $product->getTitle(),
          'number_of_boxes' => $form_values['number_of_boxes'],
          'notes' => $form_values['notes'],
        ],
      ];

      // // Check webform is open.
      $webform = Webform::load($values['webform_id']);
      $is_open = WebformSubmissionForm::isOpen($webform);

      if ($is_open === TRUE) {
        // Validate submission.
        $errors = WebformSubmissionForm::validateFormValues($values);

        // Check there are no validation errors.
        if (!empty($errors)) {
          \Drupal::logger('webform_erros')->notice('<pre><code>' . print_r($errors, TRUE) . '</code></pre>');
        } else {
          // Submit values and get submission ID.
          $webform_submission = WebformSubmissionForm::submitFormValues($values);
        }
      }

      // Sending email to user.
      $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');

      /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */

      if ($email_storage->load('offline_item_request_update')) {
        /*$user = User::load(\Drupal::currentUser()->id());*/
        $user = User::load($form_values['uid']);
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'cde_order';
        $key = 'offline_item_request_update_for_user';
        $to = $user->get('mail')->getString();
        $account_name = $user->get('name')->getString();
        $account_id = $user->get('uid')->getString();
        $site_url = \Drupal::request()->getBaseUrl();
        $product_list = $site_url . '/user/' . $user->id() . '/parts-list';

        $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');
        /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */
        $offline_item_request_update_email = $email_storage->load('offline_item_request_update');

        $email_body = '';
        if (!empty($offline_item_request_update_email->getBody())) {
          $email_body = $offline_item_request_update_email->getBody();
        }
        $offline_item_body = $email_body;

        $host = \Drupal::request()->getSchemeAndHttpHost();

        $product_list_body = str_replace("[product_list]", $product_list, $offline_item_body);
        $account_name_body = str_replace("account_name", $account_name, $product_list_body);
        $mail_to_body = str_replace("mail_to", $to, $account_name_body);
        $site_url_body = str_replace("[site_url]", $host, $mail_to_body);
        $site_url_body = str_replace("[product]", "<a href='" . $site_url . "/product/" . $form_values['product_id'] . "/edit/' >" . $product->getTitle() . "</a>", $site_url_body);
        $site_url_body = str_replace("[product_id]", $form_values['product_id'], $site_url_body);
        $site_url_body = str_replace("[number_of_boxes]", $form_values['number_of_boxes'], $site_url_body);
        $site_url_body = str_replace("[supplier_name]", $form_values['supplier_name'], $site_url_body);
        $site_url_body = str_replace("[supplier_sku]", $form_values['supplier_sku'], $site_url_body);
        $site_url_body = str_replace("[site:url]", $site_url, $site_url_body);
        $site_url_body = str_replace("[notes]", $form_values['notes'], $site_url_body);


        $params['message'] = $site_url_body;

        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $send = true;

        $to = \Drupal::config('system.site')->get('mail');
        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        if ($result['result'] !== true) {
          \Drupal::messenger()->addMessage('There was a problem sending your message and it was not sent.');
        } else {
          \Drupal::messenger()->addMessage('Your request has been sent.');

        }
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
