<?php

namespace Drupal\user_coins\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user_coins\CoinsManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a cart block.
 *
 * @Block(
 *   id = "user_coins_balance",
 *   admin_label = @Translation("User Coins Balance"),
 *   category = @Translation("User Coins")
 * )
 */
class BalanceBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * @var \Drupal\user_coins\CoinsManagerInterface
   */
  private $coinsManager;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $currentUser;

  /**
   * Class constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, CoinsManagerInterface $coins_manager, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->coinsManager = $coins_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('user_coins.coins_manager'),
      $container->get('current_user')
    );
  }

  /**
   * Builds the balance block.
   *
   * @return array
   *   A render array.
   */
  public function build() {
    $balance = $this->coinsManager->loadBalance($this->currentUser->id());

    return [
      // '#attached' => [
      //  'library' => ['user_coins/balance'],
      // ],
      '#theme' => 'user_coins_balance_block',
      '#balance' => $balance,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'use user coins');
  }

}
