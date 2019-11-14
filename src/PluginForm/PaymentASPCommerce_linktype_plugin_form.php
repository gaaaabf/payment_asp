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
  protected function buildRedirectForm(array $form, FormStateInterface $form_state, $redirect_url, array $data, $redirect_method = self::REDIRECT_GET) {    if ($redirect_method == self::REDIRECT_POST) {
      $form['#attached']['library'][] = 'commerce_payment/offsite_redirect';
      $form['#process'][] = [get_class($this), 'processRedirectForm'];
      $form['#redirect_url'] = $redirect_url;

      foreach ($data as $key => $value) {
        $form[$key] = [
          '#type' => 'hidden',
          '#value' => $value,
          // Ensure the correct keys by sending values from the form root.
          '#parents' => [$key],
        ];
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