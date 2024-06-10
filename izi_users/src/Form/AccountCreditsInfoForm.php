<?php

namespace Drupal\izi_users\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\izi_commerce\Services\CommerceCredits;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\commerce_price\Entity\Currency;
use Drupal\commerce_price\Price;
use Drupal\Core\Url;

/**
 * Account credits information form
 */
class AccountCreditsInfoForm extends FormBase {

  /**
   * use Drupal\izi_commerce\Services\CommerceCredits definition.
   *
   * @var CommerceCredits $commerceCredits
   */
  protected $commerceCredits;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var AccountProxyInterface $currentUser
   */
  protected $currentUser;

  /**
   * Class constructor.
   */
  public function __construct(CommerceCredits $commerce_credits, AccountProxyInterface $current_user) {
    $this->commerceCredits = $commerce_credits;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('izi_commerce.credits'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'izi_users_account_credits_info_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $arrNidPoints = $this->availableUsablePoints();
    $totalUsablePoints = round($arrNidPoints['total_usable_points']);
    $coin_value_amount = $this->calculateCoinValue($totalUsablePoints);

    $credits_info = $this->commerceCredits->getCredits($this->currentUser->getEmail());
    $credits = !empty($credits_info['credits']) ? $credits_info['credits'] : 0;
    $form['credits'] = [
      '#markup' => t('You have izi Credits at the moment') . " : <b> " . $totalUsablePoints . " ( " . $coin_value_amount . " ) </b> "
    ];
    $mtg_object = $this->commerceCredits->getRedeempMTG($this->currentUser->getEmail());
    $tours = '<ul>';
    foreach ($mtg_object as $uuid) {
      $tours .= '<li>' . $uuid . '</li>';
    }
    $tours .= '</ul>';
    $form['tours'] = [
      '#markup' => '<h3>Total Available Tours: </h3>' . $tours,
    ];

    // Add "Create Node" button.
    $form['create_node'] = [
      '#type' => 'submit',
      '#value' => $this->t('Assign Credit'),
      '#submit' => ['::redirectToNodeAddForm'],
    ];
    
    // Add "Add Credit" button.
    $form['add_credit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Credit'),
      '#submit' => ['::redirectToAddCreditPage'],
    ];

    return $form;
  }

  /**
   * Custom submit handler to redirect to the node add form.
   */
  public function redirectToNodeAddForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('node.add', ['node_type' => 'user_points']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Will do code here if required.
  }

  /**
   * Custom submit handler to redirect to the add credit page.
   */
  public function redirectToAddCreditPage(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::fromUri('internal:/product/63'));
  }

  /**
   * Helper function to get available usable points.
   *
   * @return array
   *   The array containing total usable points.
   */
  private function getTotalUsablePoints() {
    $arrNidPoints = availableUsablePoints();
    return round($arrNidPoints['total_usable_points']);
  }

  /**
   * Helper function to calculate coin value.
   *
   * @param float $totalUsablePoints
   *   The total usable points.
   *
   * @return string
   *   The calculated coin value amount.
   */
  protected function calculateCoinValue($totalUsablePoints) {
    $selected_currency_code = \Drupal::service('commerce_currency_resolver.current_currency')->getCurrency();
    $selected_currency = Currency::load($selected_currency_code);
    // Get the currency symbol.
    $selected_currency_symbol = $selected_currency->getSymbol();

    $coin_value = $totalUsablePoints / 10;
    $coin_value = new Price($coin_value, 'EUR');

    $coin_value_amount = \Drupal::service('commerce_currency_resolver.calculator')
      ->priceConversion($coin_value, $selected_currency_code);

    return $coin_value_amount;
  }
}
