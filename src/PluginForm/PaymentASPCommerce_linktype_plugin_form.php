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
    // Set URL
    // $postdata["success_url"] = $form['#return_url'];
    $postdata["cancel_url"] = $form['#cancel_url'];
    $postdata["error_url"] = $form['#cancel_url'];
    // $postdata["pagecon_url"] = "http://localhost/latestmultty/payment/notify/payment_asp_link_type";


	// Convert to UTF-8
	$postdata = mb_convert_encoding($postdata, 'Shift_JIS', 'UTF-8');
	// Concatenate each value
	$sps_hashcode = (String) implode('', $postdata);
	// Hashkey generation using sha1
	$sps_hashcode = sha1($sps_hashcode);
	// Adding hashkey to parameters to be passed
	$postdata['sps_hashcode'] = $sps_hashcode;

	return $this->buildRedirectForm(
	  $form,
	  $form_state,
	  // 'https://stbfep.sps-system.com/Extra/PayRequestAction.do',
	  'https://stbfep.sps-system.com/Extra/BuyRequestAction.do',
	  // 'https://stbfep.sps-system.com/f04/FepPayInfoReceive.do',
	  // 'https://stbfep.sps-system.com/f01/FepBuyInfoReceive.do',
	  $postdata,
	  'post'
	);
	}

}