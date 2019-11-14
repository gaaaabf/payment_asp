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
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\commerce_price\Price;

use Drupal\Core\Url;
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
class PaymentASPCommerce_link_type extends OffsitePaymentGatewayBase implements SupportsNotificationsInterface {



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
			
		if($this->configuration['method_type'] != NULL){
			$default_value = $this->configuration['method_type'];
		}
		else{
			$default_value = 'credit3d';
		}

			$form['method_type'] = [
			  '#title' => t('Payment Method'),
			  '#type' => 'select',
			  '#required' => TRUE,
			  '#description' => '<b>MACHINE NAME</b> above must be the same with the word inside the parenthesis ()',
			  '#default_value' => $default_value,
			  '#options' => array(
						'credit3d' => 'Credit Card with 3D Secure (credit3d)',
						'webcvs' => 'Convenience Store (webcvs)',
						'unionpay' => 'Unionpay (unionpay)',
						'paypal' => 'Paypal (paypal)',
						'alipay' => 'Alipay (alipay)',
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
		\Drupal::logger('payment_asp')->notice($request);
		ksm($request);
	}

	/**
	* {@inheritdoc}
	*/
	public function onReturn(OrderInterface $order, Request $request) {
		\Drupal::logger('payment_asp')->notice($request);
		ksm($request);
	}

	/**
	* {@inheritdoc}
	*/
	public function onNotify(Request $request) {
		\Drupal::logger('payment_asp')->notice($request);
		

	 $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
	 $order_id = str_replace("aaorderID12345", "", $request->get('order_id'));

	 if($request->get('res_result') == 'OK'){
		  	$payment = $payment_storage->create([
			  'state' 			=> 'pending',
			  'amount' 			=>  new Price('0','JPY'),
			  'payment_gateway' => $this->entityId,
			  'order_id' 		=> $order_id,
			  'test' 			=> $this->getMode() == 'test',
			  'remote_id'		=> $request->get('res_tracking_id'),
			  'remote_state'	=> empty($request->get('res_err_code')) ? 'pending' : $request->get('res_err_code'),
			  'authorized'		=> $this->time->getRequestTime(),
			]);
			$payment->save();

			$order = \Drupal\commerce_order\Entity\Order::load($order_id);   // $request->get('order_id'));
            $order->lock();
            $order->set('state', 'completed', $notify = false);
            $order->save();
      }
       	 
		 elseif($request->get('res_result') == 'PY'){

		 	 /*
		 	 *
		 	 * Parsing the res_info_key Value
		 	 *
		 	 **/

     		 $pay_info_key 			=  $request->get('res_payinfo_key');
 			 $res_payinfo_key_arr   =  explode(',', $pay_info_key);
    		 $res_code 				=  $res_payinfo_key_arr[0];
    		 $res_amount_deposit    =  $res_payinfo_key_arr[1];
    		 $res_amount_cumulative =  $res_payinfo_key_arr[2];
			 $res_email             =  $res_payinfo_key_arr[3];
		    		
		  	$payment = $payment_storage->create([
			  'state' 		    => 'completed',
			  'amount'	        => new Price($res_amount_deposit,'JPY'),
			  'payment_gateway' => $this->entityId,
			  'order_id' 		=> $order_id,
			  'test' 			=> $this->getMode() == 'test',
			  'remote_id'		=> $request->get('res_tracking_id'),
			  'remote_state' 	=> empty($request->get('res_err_code')) ? 'paid' : $request->get('res_err_code'),
			  'authorized' 		=> $this->time->getRequestTime(),
			]);
			$payment->save();
       }
       elseif($request->get('res_result') == 'CN'){

       		$order = \Drupal\commerce_order\Entity\Order::load($order_id );
            $order->delete(); 
         

       }
       $json = new JsonResponse();
	   return $json->setJson(OK);
      
	}


	/**
	* {@inheritdoc}
	*/
	public function getReturnUrl($id = NULL) {
		date_default_timezone_set('Japan');
		$order_id = \Drupal::service('payment_asp.PaymentASPController')->getOrderIdByURI();

		// $order = $payment->getOrder;
		// $id = $order->id();

		return Url::fromRoute('commerce_payment.checkout.return', [
		  'commerce_order' => $order_id,
		  'step' => 'payment',
		], ['absolute' => TRUE]);
	}

	/**
	* {@inheritdoc}
	*/
	// public function formatDigit(int $month_digit,int $day_digit , int $year_digit){
		
	// 	if($month_digit<10 && $day_digit < 10){
	// 		$month_digits = '0' . (String)$month_digit;
	// 		$day_digits = 	'0' . (String)$day_digit;
	// 	}
	// 	elseif($day_digit < 10 && $month_digit>=10){
	// 		$day_digits = '0' . (String)$day_digit;
	// 		$month_digits = (String)$month_digit;
	// 	}
	// 	elseif ($day_digit >= 10 && $month_digit<10) {
	// 		$month_digits = '0' . (String)$month_digit;
	// 		$day_digits = (String)$day_digit;
	// 	}
	// 	else{
	// 		$month_digits = (String)$month_digit;
	// 		$day_digits = (String)$day_digit;
	// 	}

	// 	return (String)$year_digit.$month_digits.$day_digits;
	// }

	/**
	* Gets the order data from Controller
	*/
	public function getOrderData(PaymentInterface $payment) {
	$payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

    $pc = \Drupal::service('payment_asp.PaymentASPController');
    $BILL_DATE   = $pc->getValidity();
 	$current_uri = \Drupal::request()->getRequestUri();
	$order 	     = $payment->getOrder();
	$id 	     = $order->id();
	$orderData   = $pc->getOrderDetails($order);
	    $givenName 				   = $order->getData("billing_profile_givenName");
	    $familyName 			   = $order->getData("billing_profile_familyName");
	    $payment_gateway_parameter = $order->getData("payment_gateway_parameter");
	switch ($this->configuration['method_type']) {
			case 'webcvs':
				$free_csv = "LAST_NAME=,FIRST_NAME=,MAIL=" . $order->getEmail(). ",TEL=" . $payment_gateway_parameter . ",BILL_DATE=" . (int)$BILL_DATE;		
				break;
			case 'credit3d':
				// $payment_gateway_parameter  = (int) $order->getData("payment_gateway_parameter");
				// if ($divide_times > 1) {
				// 	$currentDate = date('Ym');
				// 	$pay_type = 1;
				// 	$auto_charge_type = 1;
				// 	$div_settele = 0;
				// 	$last_charge_month = $currentDate;
				// }
				break;
			case 'unionpay':
				break;
		}
    
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
			"item_name"			=> "",//$orderData['orderDetail'][0]["dtl_item_name"],
			"tax"				=> "0",//$orderData['tax'],
			"amount"			=> $orderData['amount'],
			"pay_type"			=> isset($pay_type) ? $pay_type : "0",
			"auto_charge_type"	=> isset($auto_charge_type) ? $auto_charge_type : "",
			"service_type"		=> "0",
			"div_settele"		=> isset($div_settele) ? $div_settele : "",
			"last_charge_month"	=> isset($last_charge_month) ? $last_charge_month : "",
			"camp_type"			=> "",
			"tracking_id"		=> "",
			"terminal_type"		=> "0",
			"success_url"		=> $this->getReturnUrl($id)->toString(),
			"cancel_url"		=> "",
			"error_url"			=> "",
			"pagecon_url"		=> $this->getNotifyUrl()->toString(),
			"free1"				=> "",
			"free2"				=> "",
			"free3"				=> "",
			"free_csv"			=> isset($free_csv) ? $free_csv : '',	
			// 'dtl_rowno'			=> $orderData['orderDetail'][0]["dtl_rowno"],
			// 'dtl_item_id'		=> $orderData['orderDetail'][0]["dtl_item_id"],
			// 'dtl_item_name'		=> $orderData['orderDetail'][0]["dtl_item_name"],
			// 'dtl_item_count'	=> $orderData['orderDetail'][0]["dtl_item_count"],
			// 'dtl_tax'			=> $orderData['orderDetail'][0]["dtl_tax"],
			// 'dtl_amount'		=> $orderData['orderDetail'][0]["dtl_amount"],
			// 'dtl_free1'			=> $orderData['orderDetail'][0]["dtl_free1"],
			// 'dtl_free2'			=> $orderData['orderDetail'][0]["dtl_free2"],
			// 'dtl_free3'			=> $orderData['orderDetail'][0]["dtl_free3"],
			'request_date'		=> date("YmdGis"),
			"limit_second"		=> "",
			"hashkey"			=> $this->configuration['hashkey'],
		];

	foreach ($postdata as $key => $value) {
			if (\Drupal::service('payment_asp.languageCheck')->isJapanese($postdata[$key]) || strcmp($key, 'free_csv') == 0) {
				$postdata[$key] = base64_encode($postdata[$key]);
			}
		}

		return $postdata;
	}

}