<?php

namespace Drupal\user_coins\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * User coins uid validation constraint.
 *
 * @Constraint(
 *  id = "TargetUserAllowedToUseUserCoins",
 *  label = @Translation("UID from user that is not allowed.", context="Validation")
 * )
 */
class TargetUserAllowedToUseUserCoins extends Constraint {

  /**
   * {@inheritdoc}
   */
  public $message = "The user ID must be for a user that is allowed to use coins.";

}
