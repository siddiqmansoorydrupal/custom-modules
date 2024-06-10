<?php

namespace Drupal\user_coins\Plugin\views\access;

use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Drupal\views\ViewExecutable;
use Symfony\Component\Routing\Route;

/**
 * Provides custom access to views.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "MyCoinsTransactionsAccess",
 *   title = @Translation("My Coins Transactions Access"),
 *   help = @Translation("Coins transactions only accessible to current user id.")
 * )
 */
class MyCoinsTransactionsAccess extends AccessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this
      ->t('Custom User Coins Transaction Access');
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $user_param = \Drupal::routeMatch()->getParameter('user');
    if ($account->hasPermission('administer user coins transactions')) {
      return TRUE;
    }
    elseif ($account->id() == $user_param && $account->hasPermission('use user coins')) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    $route->setRequirement('_custom_access', '\Drupal\user_coins\ViewsRouteTransactionsAccessControlHandler::access');
  }

}
