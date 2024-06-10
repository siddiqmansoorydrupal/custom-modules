<?php

namespace Drupal\user_coins\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * User coins validation constraint.
 *
 * @Constraint(
 *  id = "AmountSpentBellowBalance",
 *  label = @Translation("Amount is superior to balance.", context="Validation")
 * )
 */
class AmountSpentBellowBalance extends Constraint {

  /**
   * {@inheritdoc}
   */
  public $message = "Not enough coins to cover this transaction.";

}
