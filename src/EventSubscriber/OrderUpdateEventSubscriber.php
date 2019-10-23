<?php

namespace Drupal\payment_asp\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\state_machine\Event\WorkflowTransitionEvent;


class OrderUpdateEventSubscriber implements EventSubscriberInterface {

  public function __construct() {
  
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      OrderEvents::ORDER_UPDATE => 'onOrderPlace',
    ];
    // $events = ['commerce_order.place.post_transition' => 'onOrderPlace'];
    return $events;
  }

  /**
   * Preprocessing the responsive image.
   */
  public function onOrderPlace(OrderEvent $event) {
  }
}