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
		
		// Set ERROR URL
		$postdata["cancel_url"] = $form['#cancel_url'];
		$postdata["error_url"] = $form['#cancel_url'];

		// Divide array into three
		foreach ($postdata as $key => $value) {
		    if ($key == 'lastPiece') {
					$part3[$key] = $value;
		    } elseif ($key == 'orderDetail') {
					$part2[$key] = $value;
		    } else {
	        $part1[$key] = $value;
		    }
		}


		// Convert to UTF-8
		$postdata = mb_convert_encoding($postdata, 'Shift_JIS', 'UTF-8');

		// Concatenate part1
		$sps_hashcode1 = (String) implode('', $part1);
		// Concatenate part2
		for ($i=0; $i != count($part2['orderDetail']) ; $i++) { 
			$toAppend = (String) implode('', $part2['orderDetail'][$i]);
			$sps_hashcode2 = $sps_hashcode2.$toAppend;
		}
		// Concatenate part3
		$sps_hashcode3 = (String) implode('', $part3['lastPiece']);

		// All parts concatendated
		$sps_hashcode = $sps_hashcode1.$sps_hashcode2.$sps_hashcode3;
		// Hashkey generation using sha1
		$sps_hashcode = sha1($sps_hashcode);

		unset($postdata['lastPiece']);
		
		$postdata['request_date'] = $part3['lastPiece']['request_date'];
		$postdata['limit_second'] = $part3['lastPiece']['limit_second'];
		$postdata['hashkey'] = $part3['lastPiece']['hashkey'];

		// Adding hashkey to parameters to be passed
		$postdata['sps_hashcode'] = $sps_hashcode;

		// die(var_dump($postdata));
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