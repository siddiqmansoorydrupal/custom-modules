<?php

namespace Drupal\custom_fixes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class ProductVariationFixController.
 */
class User extends ControllerBase
{

  /**
   * Main.
   *
   * @return string
   *   Return Hello string.
   */
  public function display(AccountInterface $user, Request $request)
  {
    $url = Url::fromRoute('entity.entity_view_display.user.default');
    return new RedirectResponse($url->toString());
  }

}
