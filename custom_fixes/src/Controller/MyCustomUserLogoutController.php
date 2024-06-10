<?php

namespace Drupal\custom_fixes\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MyCustomUserLogoutController extends ControllerBase {

  public function logout() {
    // Destroy the user session.
    \Drupal::service('session_manager')->destroy();

    // Get the URL of the front page.
    $url = "/";

    // Redirect to the front page.
    return new RedirectResponse($url);
  }

}
