<?php

namespace Drupal\cde_order\EventSubscriber;

use Drupal\commerce_order\Mail\OrderReceiptMailInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\user\Entity\User;

/**
 * Sends a receipt email when an order is placed.
 */
class CDEOrderReceiptSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The order receipt mail.
   *
   * @var \Drupal\commerce_order\Mail\OrderReceiptMailInterface
   */
  protected $orderReceiptMail;

  /**
   * Constructs a new CDEOrderReceiptSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_order\Mail\OrderReceiptMailInterface $order_receipt_mail
   *   The mail handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, OrderReceiptMailInterface $order_receipt_mail) {
    $this->entityTypeManager = $entity_type_manager;
    $this->orderReceiptMail = $order_receipt_mail;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = ['commerce_order.place.post_transition' => ['sendOrderReceipt', -100]];
    return $events;
  }

  /**
   * Sends an order receipt email.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The event we subscribed to.
   */
  public function sendOrderReceipt(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $email_storage = \Drupal::entityTypeManager()->getStorage('commerce_email');
    /** @var \Drupal\Core\Mail\MailManager $mailManager */
    $mailManager = \Drupal::service('plugin.manager.mail');
    $token_service = \Drupal::token();
    $langcode = \Drupal::currentUser()->getPreferredLangcode();

    if ($order->hasField('field_net_30_status')) {
      $net_30_status = $order->get('field_net_30_status')->getString();
      $curent_state = $order->get('state')->getString();
      if ($net_30_status == 'net30_open' && $curent_state == 'fulfillment') {
        // To the admin
        /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */
        $order_30_complete_to_admin_email = $email_storage->load('net_30_order_complete_to_admin');
        if ($order_30_complete_to_admin_email) {
          $module = 'cde_order';
          $key = 'new_30_on_order_complete_to_admin_custom';
          $site_mail = \Drupal::config('system.site')->get('mail');
          $to = $site_mail;
          $token_data = [
            'commerce_order' => $order,
          ];
          $token_options = ['clear' => TRUE];
          $email_body = '';
          if(!empty($order_30_complete_to_admin_email->getBody())){
            $email_body = $order_30_complete_to_admin_email->getBody();
          }
          $params['message'] = $token_service->replace($email_body, $token_data, $token_options);
          $params['order_id'] = $order->id();
          $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL);
          if ($result['result'] !== TRUE) {
            //\Drupal::logger('on_order_complete_to_admin_custom_fail')->notice('<pre><code>' . print_r('Fail', TRUE) . '</code></pre>' );
          }
          else {
            //\Drupal::logger('on_order_complete_to_admin_custom_success')->notice('<pre><code>' . print_r('Success', TRUE) . '</code></pre>' );
          }
        }

        // To the user
        /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */
        $net_30_order_complete_to_user_email = $email_storage->load('net_30_order_complete_to_user');
        if ($net_30_order_complete_to_user_email) {
          $module = 'cde_order';
          $key = 'net_30_on_order_complete_to_user_custom';
          $to = $order->getEmail();
          $token_data = [
            'commerce_order' => $order,
          ];
          $token_options = ['clear' => TRUE];
          $email_body = '';
          if(!empty($net_30_order_complete_to_user_email->getBody())){
            $email_body = $net_30_order_complete_to_user_email->getBody();
          }
          $params['message'] = $token_service->replace($email_body, $token_data, $token_options);
          $params['order_id'] = $order->id();
          // Setting email cc.
          if ($order->hasField('field_cc_email')) {
            $email_cc = $order->get('field_cc_email')->getString();
            if (!empty($email_cc) && $to != $email_cc) {
              $site_mail = \Drupal::config('system.site')->get('mail');
              $params['headers']['Cc'] = str_replace($site_mail,'', $email_cc);
            }
          }
          $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL);
          if ($result['result'] !== TRUE) {
            //\Drupal::logger('on_order_complete_to_admin_custom_fail')->notice('<pre><code>' . print_r('Fail', TRUE) . '</code></pre>' );
          }
          else {
            //\Drupal::logger('on_order_complete_to_admin_custom_success')->notice('<pre><code>' . print_r('Success', TRUE) . '</code></pre>' );
          }
        }
      }
    }

    if (\Drupal::currentUser()->isAnonymous()) {
      $to = $order->getEmail();
      $ids = \Drupal::entityQuery('user')
        ->accessCheck(FALSE)
        ->condition('mail', $to)
        ->execute();

      if (empty($ids)) {
        // Create user object.
        $user = User::create();
        // This username must be unique and accept only a-Z,0-9, - _ @.
        $user->setUsername($to);
        $user->setPassword("password");
        $user->enforceIsNew();
        $user->setEmail($to);
        //$user->addRole('authenticated'); //E.g: authenticated or administrator
        $user->activate();
        // Save user account.
        $user->save();
        $one_time_login_url = 'Click Here To Login : '. user_pass_reset_url($user);
        /** @var \Drupal\commerce_email\Entity\EmailInterface[] $emails */
        $account_create_email = $email_storage->load('account_create');
        if ($account_create_email) {
          $module = 'cde_order';
          $key = 'account_create_custom';
          $email_body = '';
          if (!empty($account_create_email->getBody())) {
            $email_body = $account_create_email->getBody();
          }
          $body = str_replace("Click Here To Login :", $one_time_login_url, $email_body);
          $token_data = [
            'commerce_order' => $order,
          ];
          $token_options = ['clear' => TRUE];
          $params['message'] = $token_service->replace($body, $token_data, $token_options);
          $params['order_user'] = $to;
          $result = $mailManager->mail($module, $key, $order->getEmail(), $langcode, $params, NULL);
          if ($result['result'] !== TRUE) {
            \Drupal::logger('account_create_email_fail')->notice('<pre><code>' . print_r($order->getEmail(), TRUE) . '</code></pre>' );
          }
          else {
            \Drupal::logger('account_create_email_success')->notice('<pre><code>' . print_r($order->getEmail(), TRUE) . '</code></pre>' );
          }
        }
      }
    }
  }

}
