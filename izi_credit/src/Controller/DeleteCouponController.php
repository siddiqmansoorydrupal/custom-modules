<?php

namespace Drupal\izi_credit\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;

class DeleteCouponController extends ControllerBase {

  public function deleteCoupon($coupon_code) {
    $connection = \Drupal::database();
    $connection->delete('izi_credit')
      ->condition('coupon_code', $coupon_code)
      ->execute();
    \Drupal::messenger()->addStatus($this->t('Coupon with code @code has been deleted.', ['@code' => $coupon_code]));

    // Redirect to the previous page
    return $this->redirect('izi_credit.form');
  }

}
