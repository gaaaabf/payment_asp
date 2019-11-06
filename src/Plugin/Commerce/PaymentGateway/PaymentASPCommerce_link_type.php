<?php

namespace Drupal\payment_asp\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\PluginForm\PaymentGatewayFormBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\payment_asp\Controller\PaymentASPController;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_order\Entity\Order;
// use ForceUTF8;
// require('Encoding.php');



/**
 * Provides the Payment ASP gateway.
 *
 * @CommercePaymentGateway(
 *   id = "payment_asp_link_type",
 *   label = "Payment ASP Link Type",
 *   display_label = "Payment ASP Link Type",
 *   forms = {
 *     "offsite-payment" = "Drupal\payment_asp\PluginForm\PaymentASPCommerce_linktype_plugin_form",
 *   },
 *   payment_type = "payment_default"
 * )
 */
class PaymentASPCommerce_link_type extends OffsitePaymentGatewayBase {


protected $postdata_success_url = 'http://stbfep.sps-system.com/MerchantPaySuccess.jsp';
protected $postdata_cancel_url = 'http://stbfep.sps-system.com/MerchantPayCancel.jsp';
protected $postdata_error_url = 'http://stbfep.sps-system.com/MerchantPayError.jsp';
protected $postdata_pagecon_url = 'http://stbfep.sps-system.com/MerchantPayResultRecieveSuccess.jsp';

	/**
	* {@inheritdoc}
	*/
	public function defaultConfiguration() {
	return [
		'method_type' => '',
		'merchant_id' => '',
		'hashkey' => '',
		'service_id' => '',
	  ] + parent::defaultConfiguration();
	}
  
	/**
	* {@inheritdoc}
	*/
	public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
		$form = parent::buildConfigurationForm($form, $form_state);

		$form['method_type'] = [
		  '#title' => t('Payment Method'),
		  '#type' => 'select',
		  '#required' => TRUE,
		  '#description' => '<b>Name Field</b> above must be the same with the word inside the parenthesis ()',
		  '#options' => array(
					'credit_card_3d' => 'Credit Card with 3D Secure (credit_card_3d)',
					'webcvs' => 'Convenience Store (webcvs)',
					'unionpay' => 'Unionpay (unionpay)',
		  ),
			];

		$form['merchant_id'] = [
		  '#type' => 'textfield',
		  '#title' => $this->t('Merchant Id'),
		  '#default_value' => $this->configuration['merchant_id'],
		  '#required' => TRUE,
		];

		$form['hashkey'] = [
		  '#type' => 'textfield',
		  '#title' => $this->t('Hashkey'),
		  '#default_value' => $this->configuration['hashkey'],
		  '#required' => TRUE,
		];

		$form['service_id'] = [
		  '#type' => 'textfield',
		  '#title' => $this->t('Service Id'),
		  '#default_value' => $this->configuration['service_id'],
		  '#required' => TRUE,
		];

		return $form;
	}

	/**
	* {@inheritdoc}
	*/
	public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
		parent::submitConfigurationForm($form, $form_state);
		$values = $form_state->getValue($form['#parents']);
		$this->configuration['method_type'] = $values['method_type'];
		$this->configuration['merchant_id'] = $values['merchant_id'];
		$this->configuration['service_id'] = $values['service_id'];
		$this->configuration['hashkey'] = $values['hashkey'];

	}

	/**
	* {@inheritdoc}
	*/
	public function onReturn(OrderInterface $order, Request $request) {}

	/**
	* {@inheritdoc}
	*/
	public function onNotify(Request $request) {
		// ksm($request);
	}

	/**
	* Gets the order data from Controller
	*/
	public function getOrderData(PaymentInterface $payment) {
	  $languageCheck = \Drupal::service('payment_asp.languageCheck');
	  $order_id = \Drupal::routeMatch()->getParameter('commerce_order')->id();
	  $order = Order::load($order_id);
	  
	  $address = $order->getBillingProfile();
	  $customer = $order->getCustomer();
   
	  $payment_method = $payment->getPaymentMethod();

  	  $telephone  = $order->getData("payment_gateway_parameter");
// die($order);

	    $pc = \Drupal::service('payment_asp.PaymentASPController');
		date_default_timezone_set('Japan');

		$order = $payment->getOrder();
		$orderData = $pc->getOrderDetails($order);
		$paymentGateway = $payment->getPaymentGateway()->id();

	
		
		$postdata = [
			'pay_method'		=> $this->configuration['method_type'],
			'merchant_id'		=> $this->configuration['merchant_id'],
			'service_id'		=> $this->configuration['service_id'],
			"cust_code"			=> $orderData['cust_code'],
			"sps_cust_no"		=> "",
			"sps_payment_no"	=> "",
			"order_id"			=> $orderData['order_id'],
			"item_id"			=> $orderData['orderDetail'][0]["dtl_item_id"],
			"pay_item_id"		=> "",
			"item_name"			=> $orderData['orderDetail'][0]["dtl_item_name"],
			"tax"				=> $orderData['tax'],
			"amount"			=> $orderData['amount'],
			"pay_type"			=> "0",
			"auto_charge_type"	=> "",
			"service_type"		=> "0",
			"div_settele"		=> "",
			"last_charge_month"	=> "",
			"camp_type"			=> "",
			"tracking_id"		=> "",
			"terminal_type"		=> "0",
			"success_url"		=> $postdata_success_url,
			"cancel_url"		=> $postdata_cancel_url,
			"error_url"			=> $postdata_error_url,
			"pagecon_url"		=> $postdata_pagecon_url,
			"free1"				=> "",
			"free2"				=> "",
			"free3"				=> "",
			"free_csv"			=> "LAST_NAME=".$orderData['free_csv_lastname'].",FIRST_NAME=".$orderData['free_csv_firstname'].",MAIL=".$orderData['free_csv_email'].",TEL=".$orderData['free_csv_telephone'],	

			'dtl_rowno'			=> $orderData['orderDetail'][0]["dtl_rowno"],
			'dtl_item_id'		=> $orderData['orderDetail'][0]["dtl_item_id"],
			'dtl_item_name'		=> $orderData['orderDetail'][0]["dtl_item_name"],
			'dtl_item_count'	=> $orderData['orderDetail'][0]["dtl_item_count"],
			'dtl_tax'			=> $orderData['orderDetail'][0]["dtl_tax"],
			'dtl_amount'		=> $orderData['orderDetail'][0]["dtl_amount"],
			'dtl_free1'			=> $orderData['orderDetail'][0]["dtl_free1"],
			'dtl_free2'			=> $orderData['orderDetail'][0]["dtl_free2"],
			'dtl_free3'			=> $orderData['orderDetail'][0]["dtl_free3"],
			'request_date'		=> date("YmdGis"),
			"limit_second"		=> "",
			"hashkey"			=> $this->configuration['hashkey'],
		];
		


	  // Check each parameter if Japanese/Chinese character
	
	  foreach ($postdata as $key => $value) {
			if ($languageCheck->isJapanese($postdata[$key])) {
				$postdata[$key] = base64_encode($postdata[$key]);
			}
	  }
	  
	  //die(var_dump($postdata));
	  	$postdata['free_csv']	  = base64_encode($postdata['free_csv']);
	  //	die(var_dump($postdata));
		// Convert to UTF-8
		$postdata = mb_convert_encoding($postdata, 'Shift_JIS', 'UTF-8');
		// Concatenate each value
		$sps_hashcode =  implode('', $postdata);
		// Hashkey generation using sha1
		$sps_hashcode = sha1($sps_hashcode);
		// Adding hashkey to parameters to be passed
		$postdata['sps_hashcode'] = $sps_hashcode;



		return $postdata;
	}

}