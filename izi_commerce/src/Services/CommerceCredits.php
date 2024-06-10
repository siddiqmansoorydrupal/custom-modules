<?php

namespace Drupal\izi_commerce\Services;

use Drupal\cognito\Aws\CognitoResult;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\cognito\Aws\CognitoInterface;
use Drupal\Core\Database\Connection;

/**
 * Class CognitoToken.
 */
class CommerceCredits {

  /**
   * The cognito service.
   *
   * @var \Drupal\cognito\Aws\CognitoInterface
   */
  protected $cognito;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The database connection service.
   * 
   * @var Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new CognitoToken object.
   *
   * @param \Drupal\cognito\Aws\CognitoInterface $cognito
   *   The cognito service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   * @param Drupal\Core\Database\Connection $connection
   *   The database connection service.
   */
  public function __construct(CognitoInterface $cognito, AccountProxyInterface $currentUser, $connection) {
    $this->cognito = $cognito;
    $this->currentUser = $currentUser;
    $this->connection = $connection;
  }

  /**
   * Validation function for the cognito user with the access token
   *
   * @param string $access_token
   *   Cognito user access token
   *
   * @return array()
   */
  public function validateAccessToken($access_token) {
    $result = [];
    if ($access_token) {
      $response = $this->cognito->getUser($access_token);
      if (!$response->hasError()) {
        $result = [
          'is_valid' => TRUE,
          'result' => $response,
        ];
        return $result;
      }
      $result = [
        'is_valid' => FALSE,
        'result' => t($response->getError())->__tostring(),
      ];
    }
    return $result;

  }

  /**
   * Get user credits
   * 
   * @param $mail
   *   The user mail id.
   * 
   * @return array()
   *   The credit information from the credit table.
   */
  public function getCredits($mail) {
    $query = $this->connection->select('user_credits', 'uc')
      ->fields('uc')
      ->condition('uc.mail', $mail)
      ->condition('uc.status', 1);
    $result = $query->execute()->fetchAssoc();

    return !empty($result) ? $result : [];
  }

  /**
   * Get user redeemed MTG
   * 
   * @param $mail
   *   The user mail id.
   * 
   * @return array()
   *   Array of the redeemped MTG object UUIDs.
   */
  public function getRedeempMTG($mail) {
    $redeem_uid = [];
    $query = $this->connection->select('user_mtg', 'um')
      ->fields('um', ['mtg_uuid'])
      ->condition('um.mail', $mail)
      ->condition('um.status', 1)
      ->orderBy('um.rid', 'DESC');
    $results = $query->execute()->fetchAllAssoc('mtg_uuid');
    if (!empty($results)) {
      foreach ($results as $key => $value) {
        $redeem_uid[] = $key;
      }
    }

    return $redeem_uid;
  }

  /**
   * Update credits values.
   */
  public function updateCredits($credits) {
    $current_time = \Drupal::time()->getRequestTime();
    if (empty($this->getCredits($this->currentUser->getEmail()))) {
      $result = $this->connection->insert('user_credits')
        ->fields(['uid', 'mail', 'credits', 'created', 'updated', 'status'])
        ->values([
          'uid' => $this->currentUser->id(),
          'mail' => $this->currentUser->getEmail(),
          'credits' => $credits,
          'created' => $current_time,
          'updated' => $current_time,
          'status' => 1,
        ])
      ->execute();
    } else {
      $credits_data = $this->getCredits($this->currentUser->getEmail());
      $new_credits = $credits_data['credits'] + $credits;
      $this->connection->update('user_credits')
        ->fields(['credits' => $new_credits, 'updated' => $current_time])
        ->condition('mail', $this->currentUser->getEmail(), '=')
      ->execute();
    }
  }
  
  public function updateCreditsApi($credits, $uid, $mail) {
    $current_time = \Drupal::time()->getRequestTime();
    if (empty($this->getCredits($mail))) {
      $result = $this->connection->insert('user_credits')
        ->fields(['uid', 'mail', 'credits', 'created', 'updated', 'status'])
        ->values([
          'uid' => $uid,
          'mail' => $mail,
          'credits' => $credits,
          'created' => $current_time,
          'updated' => $current_time,
          'status' => 1,
        ])
      ->execute();
    } else {
      $credits_data = $this->getCredits($mail);
      $new_credits = $credits_data['credits'] + $credits;
      $this->connection->update('user_credits')
        ->fields(['credits' => $new_credits, 'updated' => $current_time])
        ->condition('mail', $mail, '=')
      ->execute();
    }
	return $this->getCredits($mail);
  }
  
  

  /**
   * Redeem the credit point againts the MTG
   */
  public function redeemCredits($request_body) {
    // Check if the sufficient point is available and not expired.
    $credit_info = $this->getCredits($request_body['mail']);
    $new_credit_balance = $credit_info['credits'] - $request_body['creditPoint'];
    $current_time = \Drupal::time()->getRequestTime();
    $is_updated = $this->connection->update('user_credits')
      ->fields(['credits' => $new_credit_balance, 'updated' => $current_time])
      ->condition('mail', $request_body['mail'], '=')
      ->condition('status', 1, '=')
    ->execute();

    // Make entry into user_redeem_history table.
    if ($is_updated) {
      $rid = $this->connection->insert('user_redeem_history')
        ->fields(['mail', 'redeem_point', 'created'])
        ->values([
          'mail' => $request_body['mail'],
          'redeem_point' => $request_body['creditPoint'],
          'created' => $current_time,
        ])
      ->execute();
      // Insert record into the MTG table.
      if ($rid) {
        $this->connection->insert('user_mtg')
          ->fields(['rid', 'mail', 'mtg_uuid', 'status'])
          ->values([
            'rid' => $rid,
            'mail' => $request_body['mail'],
            'mtg_uuid' => $request_body['redeemMTG'],
            'status' => 1,
          ])
        ->execute();
      }
    }

    return true;
  }

}
