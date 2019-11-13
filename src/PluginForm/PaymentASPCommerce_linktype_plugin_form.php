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

  /**
   * Builds the redirect form.
   *
   * @param array $form
   *   The plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $redirect_url
   *   The redirect url.
   * @param array $data
   *   Data that should be sent along.
   * @param string $redirect_method
   *   The redirect method (REDIRECT_GET or REDIRECT_POST constant).
   *
   * @return array
   *   The redirect form, if $redirect_method is REDIRECT_POST.
   *
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   *   The redirect exception, if $redirect_method is REDIRECT_GET.
   */
  protected function buildRedirectForm(array $form, FormStateInterface $form_state, $redirect_url, array $data, $redirect_method = self::REDIRECT_GET) {
    if ($redirect_method == self::REDIRECT_POST) {
      $form['#attached']['library'][] = 'commerce_payment/offsite_redirect';
      $form['#process'][] = [get_class($this), 'processRedirectForm'];
      $form['#redirect_url'] = $redirect_url;

      foreach ($data as $key => $value) {
        if ($key == 'orderDetail') {
          for ($i=0; $i != count($value); $i++) {
            foreach ($value[$i] as $keys => $values) {
              $form['orderDetail'.$i][$keys] = [
                '#type' => 'textfield',
                '#value' => $values,
                // Ensure the correct keys by sending values from the form root.
                '#parents' => [$keys],
                '#size' => 3,
              ];
            }
          }
        } else {
          $form[$key] = [
            '#type' => 'textfield',
            '#value' => $value,
            // Ensure the correct keys by sending values from the form root.
            '#parents' => [$key],
          ];
        }
      }
      // The key is prefixed with 'commerce_' to prevent conflicts with $data.
      $form['commerce_message'] = [
        '#markup' => '<div class="checkout-help">' . t('Please wait while you are redirected to the payment server. If nothing happens within 10 seconds, please click on the button below.') . '</div>',
        '#weight' => -10,
      ];
    }
    else {
      $redirect_url = Url::fromUri($redirect_url, ['absolute' => TRUE, 'query' => $data])->toString();
      throw new NeedsRedirectException($redirect_url);
    }

    return $form;
  }

}