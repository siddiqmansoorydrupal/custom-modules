<?php

namespace Drupal\user_coins\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the AmountSpentBellowBalance Constraint.
 *
 * @package Drupal\user_coins\Plugin\Validation\Constraint
 */
class TargetUserAllowedToUseUserCoinsValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * Constructs a new AmountSpentBellowBalanceValidator.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The user coins manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint) {
    if (!($item = $items
      ->first())) {
      return;
    }

    $uid = $item->getValue()['target_id'];
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if ($user instanceof UserInterface && !$user->hasPermission('use user coins')) {
      $this->context->addViolation($constraint->message);
    }
  }

}
