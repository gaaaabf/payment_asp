<?php

namespace Drupal\payment_asp\PluginForm;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;


class PaymentASPCommerce_linktype_plugin_form extends BasePaymentOffsiteForm {

	/**
	* {@inheritdoc}
	*/
	public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
		$form = parent::buildConfigurationForm($form, $form_state);

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;

    /** @var \Drupal\commerce_paypal\Plugin\Commerce\PaymentGateway\ExpressCheckoutInterface $payment_gateway_plugin */
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $postdata = $payment_gateway_plugin->getOrderData($payment);

	  return $this->buildRedirectForm(
		  $form,
		  $form_state,
		  'https://stbfep.sps-system.com/Extra/PayRequestAction.do',
		  $postdata,
		  'post'
	  );
	}

}