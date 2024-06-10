<?php

namespace Drupal\custom_fixes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\user\Entity\User;

/**
 * Class ProductVariationFixController.
 */
class EmailTOCustomer extends ControllerBase
{

  /**
   * Main.
   *
   * @return string
   *   Return Hello string.
   */
  public function NotifyCustomer($uid)
  {
    //$url = Url::fromRoute('entity.entity_view_display.user.default');
    //return new RedirectResponse($url->toString());


     // Sending email to user.
     $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');

     /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */

     if ($email_storage->load('notify_customer_to_offline_product')) {
       $user = User::load($uid);
       $mailManager = \Drupal::service('plugin.manager.mail');
       $module = 'cde_order';
       $key = 'offline_item_notify_user';
       $to = $user->get('mail')->getString();


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


       $site_url = \Drupal::request()->getBaseUrl();
       $product_list = $site_url . '/user/' . $user->id() . '/parts-list';

       $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');
       /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */
       $offline_item_request_update_email = $email_storage->load('notify_customer_to_offline_product');

       $email_body = '';
       if (!empty($offline_item_request_update_email->getBody())) {
         $email_body = $offline_item_request_update_email->getBody();
       }
       $offline_item_body = $email_body;

       $host = \Drupal::request()->getSchemeAndHttpHost();

       $product_list_body = str_replace("[product_list]", $product_list, $offline_item_body);
       $account_name_body = str_replace("[account_name]", $account_name, $product_list_body);
       $account_name_body = str_replace("[Account_name]", $account_name, $product_list_body);
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
     }


    $url = Url::fromUri('internal:/user/'.$uid.'/parts-list');
    return new RedirectResponse($url->toString());
  }

}
