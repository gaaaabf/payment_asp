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
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "discover", "mastercard", "visa",
 *   },
 * )
 */
class PaymentASPCommerce_link_type extends OffsitePaymentGatewayBase {

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
	      '#type' => 'textfield',
	      '#title' => $this->t('Method Type'),
	      '#default_value' => $this->configuration['method_type'],
	      '#required' => TRUE,
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
    $order = $payment->getOrder();
		$pc = new PaymentASPController;
		$orderData = $pc->getOrderDetails($order);

		$postdata = [
	    'pay_method' => $this->configuration['method_type'],
	    'merchant_id' => $this->configuration['merchant_id'],
	    'service_id' => $this->configuration['service_id'],
	    'cust_code' => $orderData['cust_code'],
					'sps_cust_no' => '123654789111',
					'sps_payment_no' => '123',
	    'order_id' => $orderData['order_id'],
	    'item_id' => 'T_0003',
					'pay_item_id' => '1',
	    'item_name' => 'testprod',
	    'tax' => $orderData['tax'],
	    'amount' => $orderData['amount'],
	    'pay_type' => '0',
					'auto_charge_type' => '1',
	    'service_type' => '0',
					'div_settele' => '1',
					'last_charge_month' => '1',
					'camp_type' => '1',
					'tracking_id' => '1',
	    'terminal_type' => '0',
	    'success_url' => 'localhost/latestmultty/checkout/'.$orderData['order_id'].'/payment',
	    'cancel_url' => 'localhost/latestmultty/checkout/'.$orderData['order_id'].'/payment',
	    'error_url' => 'localhost/latestmultty/checkout/'.$orderData['order_id'].'/payment',
	    'pagecon_url' => 'localhost/latestmultty/checkout/'.$orderData['order_id'].'/payment',
					'free1' => '0',
					'free2' => '0',
					'free3' => '0',
	     //'free_csv' => 'LAST_NAME=鈴木,FIRST_NAME=太郎,LAST_NAME_KANA=スズキ,FIRST_NAME_KANA=タロウ,FIRST_ZIP=210,SECOND_ZIP=0001,ADD1=岐阜県,ADD2=あああ市あああ町,ADD3=,TEL=12345679801,MAIL=aaaa@bb.jp,ITEM_NAME=TEST ITEM",
	     //order.request_date          = "20191011155055',
	    'request_date' => date("Ymdhms"),
	    		'limit_second' => '1',
	    'hashkey' => $this->configuration['hashkey'],
	    'orderDetail'=> $orderData['orderDetail']
		];

	  $sps_hashcode = implode(',', $form);
	  // $sps_hashcode = mb_convert_encoding($sps_hashcode, 'Shift_JIS', 'UTF-8');
	  // $sps_hashcode = utf8_encode($sps_hashcode);
	  $sps_hashcode = sha1($sps_hashcode);

	  $postdata['sps_hashcode'] = $sps_hashcode;

		return $postdata;
	}

}