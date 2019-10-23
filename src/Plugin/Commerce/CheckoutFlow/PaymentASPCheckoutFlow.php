<?php

namespace Drupal\payment_asp\Plugin\Commerce\CheckoutFlow;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * @CommerceCheckoutFlow(
 *  id = "payment_asp_checkoutflow",
 *  label = @Translation("Payment ASP Checkoutflow"),
 * )
 */
class PaymentASPCheckoutFlow extends CheckoutFlowWithPanesBase {

  /**
   * {@inheritdoc}
   */
  public function getSteps() {
    return [
      'login' => [
        'label' => $this->t('Login'),
        'previous_label' => $this->t('Go back'),
        'has_sidebar' => FALSE,
      ],
      'order_information' => [
        'label' => $this->t('Order information'),
        'has_sidebar' => TRUE,
        'previous_label' => $this->t('Go back'),
      ],
      'review' => [
        'label' => $this->t('Review'),
        'next_label' => $this->t('Continue to review'),
        'previous_label' => $this->t('Go back'),
        'has_sidebar' => TRUE,
      ],
      'payment' => [
        'label' => $this->t('Payment'),
        'next_label' => $this->t('Pay and complete purchase'),
        'has_sidebar' => FALSE,
        'hidden' => TRUE,
      ],
      'custom_payment' => [
        'label' => $this->t('Custom Payment'),
        'next_label' => $this->t('Complete purchase'),
        'previous_label' => $this->t('Go back'),
        'has_sidebar' => FALSE,
      ],
      'complete' => [
        'label' => $this->t('Complete'),
        'next_label' => $this->t('Complete checkout'),
        'has_sidebar' => FALSE,
      ],
    ];

  }

  /**
   * {@inheritdoc}
   */
  public static function multistepSubmit(array $form, FormStateInterface $form_state) {
  	// ksm($form_state);
  }

}