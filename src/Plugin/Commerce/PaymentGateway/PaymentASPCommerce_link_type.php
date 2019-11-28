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

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Serializer\Serializer;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsNotificationsInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\commerce_price\Price;
use Drupal\Core\Url;
use GuzzleHttp\Client;

/**
 * Provides the Payment ASP gateway.
 *
 * @CommercePaymentGateway(
 *   id = "payment_asp_link_type",
 *   label = "Payment ASP Link Type",
 *   display_label = "Payment ASP Link Type",
 *   forms = {
 *     "offsite-payment" = "Drupal\payment_asp\PluginForm\PaymentASPCommerce_linktype_plugin_form",
 *
 *   },
 *   payment_type = "payment_default"
 * )
 */
class PaymentASPCommerce_link_type extends OffsitePaymentGatewayBase implements SupportsNotificationsInterface, SupportsRefundsInterface {

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
  public function refundPayment(PaymentInterface $payment, Price $amount = NULL) {
    $postdata = \Drupal::service('payment_asp.PaymentASPController')->getRefundDetails($this->configuration['merchant_id'], $this->configuration['service_id'], $payment, $amount);
    $username = $this->configuration['merchant_id'] . $this->configuration['service_id'];
    $password = $this->configuration['hashkey'];

    // 接続URL
    $url = "https://stbfep.sps-system.com/api/xmlapi.do";

    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    // $client = new Client();
    // $res = $client->request('POST', $url, [
    //     'auth' => [$username, $password]
    // ]);

    // Perform the refund request here, throw an exception if it fails.
    try {
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
      ]);
      $content = $response->getContent();
      $xml = simplexml_load_string($content);
      $result = (string) $xml->res_result;
    } catch (\Exception $e) {
      \Drupal::logger('payment_asp')->notice('Error message about the failure');
      throw new PaymentGatewayException('Error message about the failure');
    }

    ksm($content);

    // if ($result == 'OK') {
    //   // Determine whether payment has been fully or partially refunded.
    //   $old_refunded_amount = $payment->getRefundedAmount();
    //   $new_refunded_amount = $old_refunded_amount->add($amount);

    //   if ($new_refunded_amount->lessThan($payment->getAmount())) {
    //     $payment->setState('partially_refunded');
    //   }
    //   else {
    //     $payment->setState('refunded');
    //   }

    //   $payment->setRefundedAmount($new_refunded_amount);
    //   $payment->save();
    // }

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

    $form['service_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Service Id'),
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
  }

  /**
  * {@inheritdoc}
  */
  public function getReturnUrl($id = NULL) {
    if (is_null($id)) {
      $id = \Drupal::service('payment_asp.PaymentASPController')->getOrderIdByURI();
    }
    return Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $id,
      'step' => 'payment',
    ], ['absolute' => TRUE]);
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
     drupal_set_message(t('An error occurred with the payment. Please try again.'), 'error');
    \Drupal::logger('payment_asp')->notice('ON CANCEL FUNCTION '.$request);
  }

  /**
  * {@inheritdoc}
  */
  public function onReturn(OrderInterface $order, Request $request) {
    drupal_set_message(t('Payment is successful'), 'success');
    \Drupal::logger('payment_asp')->notice('ON RETURN FUNCTION '.$request);
  }

  /**
  * {@inheritdoc}
  */
  public function onNotify(Request $request) {
    $connection = \Drupal::database();
    \Drupal::logger('payment_asp')->notice($request);

    if ($this->configuration['method_type'] == 'webcvs') {    
      if ($request->get('res_result') == 'OK') {
        $this->createPayment($request, 'Pending', 0);
        $this->completeOrder(substr($request->get('order_id'), 0, -4));
      } elseif ($request->get('res_result') == 'PY') {

        $pay_info_key = $request->get('res_payinfo_key');
        $res_payinfo_key_arr = explode(',', $pay_info_key);
        $res_code = $res_payinfo_key_arr[0];
        $res_amount_deposit = $res_payinfo_key_arr[1];
        $res_amount_cumulative = $res_payinfo_key_arr[2];
        $res_email = $res_payinfo_key_arr[3];
            
        $this->createPayment($request, 'completed', $res_amount_deposit);

       } elseif ($request->get('res_result') == 'CN') {
          \Drupal::logger('payment_asp')->notice('CANCEL/EXPIRY '.$request);
          $order = \Drupal\commerce_order\Entity\Order::load(substr($request->get('order_id'), 0, -4));
          $order->delete();
       }
    } else {
      $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');

      if ($request->get('res_result') == 'OK') {
        $result = $this->validateTrackingID($request->get('res_tracking_id'));
        if ($result) {
          return $request;
        } else {
          $this->createPayment($request, 'completed');
          // $this->completeOrder(substr($request->get('order_id'), 0, -4));
        }
      } elseif ($request->get('res_result') == 'NG') {
        $order = \Drupal\commerce_order\Entity\Order::load($request->get('order_id'));
        $order->unlock();
        $order->save();
      }
    }
    $json = new JsonResponse();
    return $json->setJson('OK');
  }

  /**
  * Create payment
  */
  public function createPayment(Request $request, $state, $amount = NULL) {
    $connection = \Drupal::database();
    if (is_null($amount)) {
      $amount = $request->get('amount');
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'state'       => $state,
      'amount'      =>  new Price($amount ,'JPY'),
      'payment_gateway' => $this->entityId,
      'order_id'    => substr($request->get('order_id'), 0, -4),
      'test'      => $this->getMode() == 'test',
      'remote_id'   => $request->get('res_tracking_id'),
      'remote_state'  => empty($request->get('res_err_code')) ? $state : $request->get('res_err_code'),
      'authorized'    => $this->time->getRequestTime(),
    ]);
    $payment->save();

    $connection->insert('payment_asp_pd')
      ->fields(array(
       'p_fk_id' => $payment->id(),
       'tracking_id' => (string) $request->get('res_tracking_id'),
       'sps_transaction_id' => (int) $request->get('res_sps_transaction_id'),
       'processing_datetime' => (int) $request->get('res_process_date'),
      ))->execute();
  }

  /**
  * Completes Order
  */
  public function completeOrder($order_id, $state = 'complete') {
    $order = \Drupal\commerce_order\Entity\Order::load($order_id);
    $order->set('checkout_step', $state);
    $order->set('cart', 0);
    $order->getState()->applyTransitionById('place');
    $order->save();

    // $order->unlock();
    // $order->set('state', $state, $notify = false);
    // $order->save();  
  }

  /**
  * Validate if tracking ID already exists
  */
  public function validateTrackingID($tracking_id) {
    $connection = \Drupal::database();
    $check_tracking_id = $connection->select('commerce_payment', 'remote_id')->fields('remote_id', ['remote_id'])->condition('remote_id', $tracking_id, '=')->execute()->fetchAll();
    if (count($check_tracking_id) > 0) {
      return TRUE;
    } else {
      return FALSE;
    }
  }   

  /**
  * Gets the order data from Controller
  */
  public function getOrderData(PaymentInterface $payment) {
    $pc = \Drupal::service('payment_asp.PaymentASPController');
    date_default_timezone_set('Japan');

    // Order data from database
    $order = $payment->getOrder();
    // Common order data parameters
    $orderData = $pc->getOrderDetails($order);
    // Optional payment gateway paramater
    $payment_gateway_parameter = $order->getData("payment_gateway_parameter");

    switch ($this->configuration['method_type']) {
      case 'webcvs':
        $givenName = $order->getData("billing_profile_givenName");
        $familyName = $order->getData("billing_profile_familyName");
        $kana_givenName = mb_convert_kana($givenName, "NRS","UTF-8");
        $kana_familyName = mb_convert_kana($familyName, "NRS","UTF-8");
        $BILL_DATE = $pc->getValidity();
        $free_csv = "LAST_NAME=" . $kana_familyName . ",FIRST_NAME=" . $kana_givenName . ",MAIL=" . $order->getEmail() . ",TEL=" . $payment_gateway_parameter . ",BILL_DATE=" .  (int)$BILL_DATE;
        break;
      case 'credit3d':
        if (FALSE) {
          $currentDate = date('Ym', strtotime("+3 months", strtotime($currentDate)));
          $pay_type = 1;
          $auto_charge_type = 1;
          $div_settele = 0;
          $last_charge_month = $currentDate;
          $camp_type = 0;
        }
        break;
      }
    
    $postdata = [
      'pay_method'    => $this->configuration['method_type'],
      'merchant_id'   => $this->configuration['merchant_id'],
      'service_id'    => $this->configuration['service_id'],
      "cust_code"     => $orderData['cust_code'],
      "sps_cust_no"   => "",
      "sps_payment_no"  => "",
      "order_id"      => $orderData['order_id'].date('is'),
      "item_id"     => $orderData['orderDetail'][0]["dtl_item_id"],
      "pay_item_id"   => "",
      "item_name"     => "",
      // "item_name"     => $orderData['orderDetail'][0]["dtl_item_name"],
      "tax"       => $orderData['tax'],
      "amount"      => $orderData['amount'],
      "pay_type"      => isset($pay_type) ? $pay_type : "0",
      "auto_charge_type"  => isset($auto_charge_type) ? $auto_charge_type : "",
      "service_type"    => "0",
      "div_settele"   => isset($div_settele) ? $div_settele : "",
      "last_charge_month" => isset($last_charge_month) ? $last_charge_month : "",
      "camp_type"     => isset($camp_type) ? $camp_type : "",
      "tracking_id"   => "",
      "terminal_type"   => "0",
      "success_url"   => $this->getReturnUrl($order->id())->toString(),
      "cancel_url"    => "",
      "error_url"     => "",
      "pagecon_url"   => $this->getNotifyUrl()->toString(),
      "free1"       => "",
      "free2"       => "",
      "free3"       => "",
      "free_csv"      => isset($free_csv) ? $free_csv : '',
      // Will not be using this for now
      // 'dtl_rowno'     => $orderData['orderDetail'][0]["dtl_rowno"],
      // 'dtl_item_id'   => $orderData['orderDetail'][0]["dtl_item_id"],
      // 'dtl_item_name'   => $orderData['orderDetail'][0]["dtl_item_name"],
      // 'dtl_item_count'  => $orderData['orderDetail'][0]["dtl_item_count"],
      // 'dtl_tax'     => $orderData['orderDetail'][0]["dtl_tax"],
      // 'dtl_amount'    => $orderData['orderDetail'][0]["dtl_amount"],
      // 'dtl_free1'     => $orderData['orderDetail'][0]["dtl_free1"],
      // 'dtl_free2'     => $orderData['orderDetail'][0]["dtl_free2"],
      // 'dtl_free3'     => $orderData['orderDetail'][0]["dtl_free3"],
      'request_date'    => date("YmdGis"),
      "limit_second"    => "",
      "hashkey"     => $this->configuration['hashkey'],
    ];
    
    return $postdata;
  }

}