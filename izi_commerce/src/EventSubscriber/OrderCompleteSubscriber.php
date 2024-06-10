<?php

namespace Drupal\izi_commerce\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\izi_commerce\Services\CommerceCredits;

/**
 * Class OrderCompleteSubscriber.
 *
 * @package Drupal\izi_commerce
 */
class OrderCompleteSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\izi_commerce\Services\CommerceCredits definition.
   * 
   * @var Drupal\izi_commerce\Services\CommerceCredits
   */
  protected $commerceCredits;

  /**
   * Constructor.
   */
  public function __construct(EntityTypeManager $entity_type_manager, CommerceCredits $commerce_credits) {
    $this->entityTypeManager = $entity_type_manager;
    $this->commerceCredits = $commerce_credits;
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events['commerce_order.place.post_transition'] = ['orderCompleteHandler'];

    return $events;
  }

  /**
   * This method is called whenever the commerce_order.place.post_transition event is
   * dispatched.
   *
   * @param WorkflowTransitionEvent $event
   */
  public function orderCompleteHandler(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    // Order items in the cart.
    $items = $order->getItems();
    $credits = 0;
    foreach ($items as $item) {
      if ($item->get('type')->target_id == 'default') {
        //$credits = $item->getPurchasedEntity()->get('field_credits')->value;
      }
    }

    // Call update credits service call.
    $this->commerceCredits->updateCredits($credits);
  }
  
}