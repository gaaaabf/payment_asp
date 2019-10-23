<?php

namespace Drupal\payment_asp\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

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
	public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
		$comment = $this->order->getData('order_comment');
		$pane_form['comment'] = [
		  '#type' => 'textfield',
		  '#title' => $this->t('Optional order comment'),
		  '#default_value' => $comment ? $comment : '',
		  '#size' => 60,
		];
		return $pane_form;
	}

	public function buildPaneSummary() {
		if ($order_comment = $this->order->getData('order_comment')) {
			return [
		  '#plain_text' => $order_comment,
			];
		}
		return [];
	}

}