<?php

namespace Drupal\payment_asp\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;



/**
 * Provides the payment process pane for Payment ASP.
 *
 * @CommerceCheckoutPane(
 *   id = "payment_asp_checkoutpane_add_info",
 *   label = @Translation("Payment ASP Comments/Notes and Packaging"),
 *   wrapper_element = "fieldset", 
 * )
 */
class PaymentASPCheckoutPane_add_info extends CheckoutPaneBase {

  public function isVisible() {
    return TRUE;
  }

  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['comment'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Optional order comment'),
      '#default_value' => '',
      '#size' => 60,
    ];

    $pane_form['notes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Optional order notes'),
      '#default_value' => '',
      '#size' => 60,
    ];

    return $pane_form;
  }

  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {

  }

}