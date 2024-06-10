<?php

namespace Drupal\izi_cognito\Form;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Izi cognito form.
 */
class EditCognitoProfile extends FormBase {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * @var \Aws\CognitoIdentityProvider\CognitoIdentityProviderClient
   */
  protected $client;

  /**
   * @param \Drupal\Core\Session\AccountInterface $account
   */
  public function __construct(AccountInterface $account) {
    $this->account = $account;
    $this->settings = Settings::get('cognito');
    $this->client = new CognitoIdentityProviderClient($this->settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_cognito_edit_cognito_profile';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get drupal current user email.
    $current_user_email = $this->account->getEmail();

    try {
      // Get cognito user data.
      $result = $this->client->adminGetUser([
        'UserPoolId' => $this->settings['user_pool_id'],
        'Username' => $current_user_email,
      ]);
      $cognito_user_attributes = $result['UserAttributes'] ?? [];

      // Build form.
      $form['display_name'] = [
        '#type' => 'textfield',
        '#title' => t('Display Name'),
        '#default_value' => $this->getCognitoUserAttribute($cognito_user_attributes, "custom:Name"),
      ];

      $form['dob'] = [
        '#type' => 'date',
        '#title' => $this->t('Date of Birth'),
        '#attributes' => ['class' => ['form-control']],
        '#default_value' => $this->getCognitoUserAttribute($cognito_user_attributes, "custom:birthday"),
      ];

      $form['phone'] = [
        '#type' => 'textfield',
        '#title' => t('Phone Number'),
        '#default_value' => $this->getCognitoUserAttribute($cognito_user_attributes, "custom:Phone"),
      ];

      $form['actions'] = [
        '#type' => 'actions',
      ];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send'),
      ];
    } catch (\Aws\Exception\AwsException $e) {
      $this->messenger()->addError($e->getAwsErrorMessage());
    } catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }

    return $form;
  }

  /**
   * Get cognito user attribute value by key.
   */
  private function getCognitoUserAttribute($attributes, $searchKey) {
    $keyIndex = array_search($searchKey, array_column($attributes, "Name"));
    return $keyIndex ? $attributes[$keyIndex]["Value"] : '';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    //...
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      // Send user data to cognito.
      $this->client->adminUpdateUserAttributes([
        'Username' => $this->account->getEmail(),
        'UserPoolId' => $this->settings['user_pool_id'],
        'UserAttributes' => [
          ['Name' => 'custom:Name','Value' => $form_state->getValue('display_name')],
          ['Name' => 'custom:birthday','Value' => $form_state->getValue('dob')],
          ['Name' => 'custom:Phone','Value' => $form_state->getValue('phone')],
        ],
      ]);

      $this->messenger()->addStatus($this->t('The updated data has been sent.'));
    } catch (\Aws\Exception\AwsException $e) {
      $this->messenger()->addError($e->getAwsErrorMessage());
    } catch (\Exception $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

}
