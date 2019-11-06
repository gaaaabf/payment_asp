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

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Serializer\Serializer;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;



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
class PaymentASPCommerce_link_type extends OffsitePaymentGatewayBase implements SupportsNotificationsInterface{

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
						'credit3d' => 'Credit Card with 3D Secure (credit3d)',
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
	public function onCancel(OrderInterface $order, Request $request) {
		ksm($request);
	}

	/**
	* {@inheritdoc}
	*/
	public function onReturn(OrderInterface $order, Request $request) {
		die('kaning');
		ksm($request);
	}

	/**
	* {@inheritdoc}
	*/
	public function onNotify(Request $request) {
		\Drupal::logger('payment_asp')->notice($request);
		// return 'SUCCESSSS';
	}

	/**
	* Gets the order data from Controller
	*/
	public function getOrderData(PaymentInterface $payment) {
	  $languageCheck = \Drupal::service('payment_asp.languageCheck');
	  $pc = \Drupal::service('payment_asp.PaymentASPController');
		$current_uri = \Drupal::request()->getRequestUri();
		date_default_timezone_set('Japan');

		$order = $payment->getOrder();
		$orderData = $pc->getOrderDetails($order);

		$paymentGateway = $payment->getPaymentGateway()->id();

		switch ($paymentGateway) {
			case 'credit_card_3d':
				// DEALINGS_TYPE
				// DIVIDE_TIMES 
				// ONE_TIME_CHARGE_CUSTOMER_REGIST
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
					"success_url"		=> $this->getNotifyUrl()->toString(),
					"cancel_url"		=> "",
					"error_url"			=> "",
					"pagecon_url"		=> $this->getNotifyUrl()->toString(),
					"free1"				=> "",
					"free2"				=> "",
					"free3"				=> "",
					"free_csv"			=> "LAST_NAME=鈴木,FIRST_NAME=太郎,LAST_NAME_KANA=スズキ,FIRST_NAME_KANA=タロウ,FIRST_ZIP=210,SECOND_ZIP=0001,ADD1=岐阜県,ADD2=あああ市あああ町,ADD3=,TEL=12345679801,MAIL=aaaa@bb.jp,ITEM_NAME=TEST ITEM",
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

				break;
			
			case 'webcvs':
				break;
		}

		// Check each parameter if Japanese/Chinese character
		foreach ($postdata as $key => $value) {
			if ($languageCheck->isJapanese($postdata[$key] || $key == 'free_csv')) {
				$postdata[$key] = base64_encode($postdata[$key]);
			}
		}
		
		return $postdata;
	}

}