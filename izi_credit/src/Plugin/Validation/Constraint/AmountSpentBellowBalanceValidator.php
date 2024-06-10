<?php

namespace Drupal\user_coins\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\user_coins\CoinsManagerInterface;
use Drupal\user_coins\Entity\TransactionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the AmountSpentBellowBalance Constraint.
 *
 * @package Drupal\user_coins\Plugin\Validation\Constraint
 */
class AmountSpentBellowBalanceValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * @var \Drupal\user_coins\CoinsManagerInterface
   */
  private $coinsManager;

  /**
   * Constructs a new AmountSpentBellowBalanceValidator.
   *
   * @param \Drupal\user_coins\CoinsManagerInterface $coins_manager
   *   The user coins manager.
   */
  public function __construct(CoinsManagerInterface $coins_manager) {
    $this->coinsManager = $coins_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('user_coins.coins_manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    if (!($item = $items
      ->first())) {
      return;
    }

    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = $items
      ->getEntity();

    if ($entity instanceof TransactionInterface && $entity->getType() == 'spend') {
      $balance = $this->coinsManager->loadBalance($entity->getOwnerId());
      $transaction_amount = $item->value;
      if ($transaction_amount > $balance) {
        $this->context->addViolation($constraint->message);
      }
    }
  }

}
