<?php

namespace Drupal\payment_asp\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\Core\Url;

/**
 * Provides a custom message pane.
 *
 * @CommerceCheckoutPane(
 *   id = "payment_asp_checkoutpane",
 *   label = @Translation("Payment ASP Checkoutpane"),
 * )
 */
class PaymentASPCheckoutPane extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    if ($this->order->payment_gateway->entity !== null) {
      $payment_gateway = $this->order->payment_gateway->entity->getPluginId();
    }
    if (isset($payment_gateway) && $payment_gateway == 'payment_asp_link_type') {
      return false;
    } else {
      return true;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $this->order->payment_gateway->entity;
    $payment_gateway_plugin = $payment_gateway->getPlugin();
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $payment_storage->create([
      'state' => 'new',
      'amount' => $this->order->getBalance(),
      'payment_gateway' => $payment_gateway->id(),
      'order_id' => $this->order->id(),
    ]);

    if ($payment_gateway_plugin->getPluginId() == 'payment_asp_link_type') {
      $next_step_id = $this->checkoutFlow->getNextStepId($this->getStepId());
    } elseif ($payment_gateway_plugin->getPluginId() == 'payment_asp_credit_card') {

      $next_step_id = $this->checkoutFlow->getNextStepId($this->getStepId());
      $result = $payment_gateway_plugin->createPayment($payment);

        if($result == 'NG') {
          $this->redirectToCart();
        } elseif($result == 'OK') {
          // Create payment to save to database
          $payment->setState($next_state);
          $payment->setRemoteId($response->transaction->id);
          $payment->setExpiresTime(strtotime('+5 days'));
          $payment->save();

          $field_arr = [
            'p_fk_id' => $payment->id(),
            'tracking_id' => (string) $xml->res_tracking_id,
            'sps_transaction_id' => (string) $xml->res_sps_transaction_id,
            'processing_datetime' => (string) $xml->res_process_date,
          ];
          // Save to database
          $query = \Drupal::database();
          $query->insert('payment_asp_pd')
                ->fields($field_arr)
                ->execute();
          unset($_SESSION["cc_data"]);
          $this->checkoutFlow->redirectToStep($next_step_id);
        }
    }

  }

  /**
   * Redirect to cart in case of a PaymentGatewayException exception.
   */
  protected function redirectToCart() {
    drupal_set_message('Payment has not gone through. Please check you credit card detials', 'error');
    $this->order->get('checkout_flow')->setValue(NULL);
    $this->order->get('checkout_step')->setValue('review');
    $this->order->unlock();
    $this->order->save();
    throw new NeedsRedirectException(Url::fromRoute('commerce_cart.page')->toString());
  }

}