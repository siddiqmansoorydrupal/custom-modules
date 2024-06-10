<?php

namespace Drupal\cde_order\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Send a notification to a user.
 *
 * @Action(
 *   id = "user_offline_items_notification_action",
 *   label = @Translation("Products offline items notification to the selected users"),
 *   type = "user"
 * )
 */
class OfflineItemsNotification extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($account = NULL) {
    // Skip blocking user if they are already blocked.
    if ($account !== FALSE && $account->isActive()) {
      $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');
      /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */
      $email = $email_storage->load('ne_30_order_shipping_confirmation_to_user');
      if ($email) {
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'cde_order';
        $key = 'offline_items_notification_for_users';
        $to = $account->get('mail')->getString();
        $account_name = $account->get('name')->getString();
        $account_id = $account->get('uid')->getString();
        $site_url = \Drupal::request()->getBaseUrl();
        $product_list = $site_url. '/user/' . $account_id . '/parts-list';

        $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');
        /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */
        $quote_list_update_cde_fasteners_email = $email_storage->load('quote_list_update_cde_fasteners');
        $email_body = '';
        if (!empty($quote_list_update_cde_fasteners_email->getBody())) {
          $email_body = $quote_list_update_cde_fasteners_email->getBody();
        }
        $quote_body = $email_body;
        $update_part_list = str_replace("account_name", $account_name,$quote_body);
        $update_account_name = str_replace("product_list",$product_list,$update_part_list);
        $params['message'] = $update_account_name;
        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL);
        if ($result['result'] !== TRUE) {
          \Drupal::messenger()->addMessage('There was a problem sending your message and it was not sent.');
        }
        else {
          \Drupal::messenger()->addMessage('Your message has been sent.');
        }
      }

    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $object->access('update', $account, TRUE);
    return $return_as_object ? $result : $result->isAllowed();
  }

}
