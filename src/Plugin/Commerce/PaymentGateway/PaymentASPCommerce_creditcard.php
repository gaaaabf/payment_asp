<?php

namespace Drupal\payment_asp\Plugin\Commerce\PaymentGateway;

require_once 'HTTP_Request2-trunk/HTTP/Request2.php';
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\payment_asp\Controller\PaymentASPController;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Serializer\Serializer;

/**
 * Provides the Payment ASP gateway.
 *
 * @CommercePaymentGateway(
 *   id = "payment_asp_credit_card",
 *   label = "Payment ASP Credit Card",
 *   display_label = "Payment ASP Credit Card",
 *   forms = {
 *     "input_cc_details" = "Drupal\payment_asp\Form\payment_creditcard",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "discover", "mastercard", "visa",
 *   },
 * )
 */
class PaymentASPCommerce_creditcard extends OnsitePaymentGatewayBase {

	/**
	* Gets current payment details
	*/	
	protected $payment_details = [];

	/**
	* {@inheritdoc}
	*/
	public function defaultConfiguration() {
	return [
		'merchant_id' => '',
		'service_id' => '',
		'hashkey' => '',
	  ] + parent::defaultConfiguration();
	}
  
  	/**
   	* {@inheritdoc}
   	*/
	public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
		$form = parent::buildConfigurationForm($form, $form_state);

	    $form['merchant_id'] = [
	      '#type' => 'textfield',
	      '#title' => $this->t('Merchant id'),
	      '#default_value' => $this->configuration['merchant_id'],
	      '#required' => TRUE,
	    ];

	    $form['service_id'] = [
	      '#type' => 'textfield',
	      '#title' => $this->t('Service id'),
	      '#default_value' => $this->configuration['service_id'],
	      '#required' => TRUE,
	    ];

	    $form['hashkey'] = [
	      '#type' => 'textfield',
	      '#title' => $this->t('Hashkey'),
	      '#default_value' => $this->configuration['hashkey'],
	      '#required' => TRUE,
	    ];

	    return $form;
		// return $this->buildRedirectForm($form, $form_state);
	}

  	/**
   	* {@inheritdoc}
   	*/
	public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
		parent::submitConfigurationForm($form, $form_state);
	    $values = $form_state->getValue($form['#parents']);
	    $this->configuration['merchant_id'] = $values['merchant_id'];
	    $this->configuration['service_id'] = $values['service_id'];
	    $this->configuration['hashkey'] = $values['hashkey'];
	}

  	/**
   	* {@inheritdoc}
   	*/
	public function createPayment(PaymentInterface $payment, $capture = TRUE) {
		$payment_method = $payment->getPaymentMethod();

		if (isset($payment_method)) {
			$payment_method->delete();
		}

		$amount = $payment->getAmount()->getNumber();
		$currency_code = $payment->getAmount()->getCurrencyCode();
		$order = $payment->getOrder();
		$order_id = $payment->getOrderId();

		$username = $this->configuration['merchant_id'] . $this->configuration['service_id'];
		$password = $this->configuration['hashkey'];

		$pc = new PaymentASPController;
		$postdata = $pc->getOrderDeatilsAPI($this->configuration['merchant_id'], $this->configuration['service_id'], $this->configuration['hashkey']);

		// 接続URL
		$url = "https://stbfep.sps-system.com/api/xmlapi.do";

		// データ送信処理
		$client = HttpClient::create([
		    'auth_basic' => [$username, $password],
		]);
		$response = $client->request('POST', $url, [
		    'headers' => [
		        'Content-Type' => 'text/xml',
		        'Cache-Control' => 'no-cache',
		        'Pragma' => 'no-cache',
		        'Expires' => '0',
		    ],
		    'body' => $postdata,
		    'timeout' => 9,
		]);
		$content = $response->getContent();
		$statusCode = $response->getStatusCode();
		$xml = simplexml_load_string($content);
		$result = (string) $xml->res_result;

		// $response->cancel();
		// $payment->setState($next_state);
		// $payment->setRemoteId($response->transaction->id);
		// $payment->setExpiresTime(strtotime('+5 days'));
		// $payment->save();
		
		$response->__destruct();

		ksm($content);
		ksm($response);

		unset($_SESSION["cc_data"]);

		if($result == 'NG') {
		} elseif ($result == 'OK') {
			$field_arr = [
				'tracking_id' => (string) $xml->res_tracking_id,
				'sps_transaction_id' => (string) $xml->res_sps_transaction_id,
				'processing_datetime' => (string) $xml->res_process_date,
			];
			// Save to database
			$query = \Drupal::database();
			$query->insert('payment_asp_pd')
				->fields($field_arr)
				->execute();
		}

	}

  	public function capturePayment(PaymentInterface $payment, Price $amount = NULL) {
  		die('sa capture payment');
  	}

  	/**
   	* {@inheritdoc}
   	*/
	public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
		ksm($payment_details);
		session_start();
		$_SESSION["cc_data"] = [
			'number' => $payment_details['number'],
			'security_code' => $payment_details['security_code'],
			'expiration' => $payment_details['expiration']['year'] . $payment_details['expiration']['month'],
		];
	}

  	/**
   	* {@inheritdoc}
   	*/
	public function deletePaymentMethod(PaymentMethodInterface $payment_method) {

	}

}