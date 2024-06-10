<?php

namespace Drupal\izi_users\Services;

use Drupal\Core\Session\AccountProxyInterface;

/**
 * Class UserHelperSerice.
 */
class UserHelperService {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   */
  public function __construct(AccountProxyInterface $currentUser) {
    $this->currentUser = $currentUser;
  }


  /**
   * Helper service function for to format the user attributes.
   */
  public function userAttributeFormatter($response) {
    $user_data = [];
    if (!$response->hasError()) {
      $user_attributes = $response->getResult()['UserAttributes'];
      if (!empty($user_attributes)) {
        foreach($user_attributes as $index => $data) {
          $user_data[$data['Name']] = $data['Value']; 
        }
      }
    }
    
    return $user_data; 
  }

}