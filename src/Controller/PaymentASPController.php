<?php
  namespace Drupal\payment_asp\Controller;

  class PaymentASPController {

	  public function __construct() {
	  
	  }

    public function getOrderDetails($order) {
    	// $uid = \Drupal::currentUser()->id();
    	// $current_uri = \Drupal::request()->getRequestUri();
    	// $current_uri = explode('/', $current_uri);
    	// $order_id = $current_uri[3];

    	// $order = \Drupal\commerce_order\Entity\Order::load($order_id);

      $order_id = $order->id();

      $orderDetail = [];
      $perItem = [];
      $items = $order->getItems();

      for ($i=0; $i != count($items); $i++) { 
        $perItem['dtl_rowno'] = $i+1;
        $perItem['dtl_item_id'] = $items[$i]->id();
        $perItem['dtl_item_name'] = $items[$i]->label();
        $perItem['dtl_item_count'] = (int)$items[$i]->getQuantity();

        $taxEntity = $items[$i]->getAdjustments(array('tax'));
        $taxPercentage = $taxEntity[0]->getPercentage()*100;
        $perItem['dtl_tax'] = (string)$taxPercentage.'%';

        array_push($orderDetail, $perItem);
        // $orderDetail['orderDetail'.$i] = $perItem;
      }

      $data_needed = array(
        'order_id' => $order_id,
        'cust_code' => $order->getCustomer()->getDisplayName(),
        'amount' => $order->getTotalPrice()->getNumber(),
        'tax' => $perItem['dtl_tax'],
        'orderDetail' => $orderDetail,
      );

      return $data_needed;
    }

    public function getOrderDeatilsAPI($merchant_id, $service_id, $hashkey, $order) {

      $data = $this->getOrderDetails($order);

      // API送信データ
      $merchant_id              = $merchant_id;
      $service_id               = $service_id;
      $cust_code                = $data['cust_code'].date("Ymdhms");
      $order_id                 = $data['order_id'];
      $item_id                  = "ITEMID00000000000000000000000001";
      $item_name                = "テスト商品";
      $tax                      = "1";
      $amount                   = "1";
      $free1                    = "";
      $free2                    = "";
      $free3                    = "";
      $order_rowno              = "";
      $sps_cust_info_return_flg = "1";
      $cc_number                = $_SESSION["cc_data"]['number'];
      $cc_expiration            = $_SESSION["cc_data"]['expiration'];
      $security_code            = $_SESSION["cc_data"]['security_code'];
      // $cc_number                = "5250729026209007";
      // $cc_expiration            = "201103";
      // $security_code            = "798";
      $cust_manage_flg          = "0";
      $encrypted_flg            = "0";
      $request_date             = date("Ymdhms");
      $limit_second             = "";
      $hashkey                  = $hashkey;

      // Shift_JIS変換
      $merchant_id              = mb_convert_encoding($merchant_id, 'Shift_JIS', 'UTF-8');
      $service_id               = mb_convert_encoding($service_id, 'Shift_JIS', 'UTF-8');
      $cust_code                = mb_convert_encoding($cust_code, 'Shift_JIS', 'UTF-8');
      $order_id                 = mb_convert_encoding($order_id, 'Shift_JIS', 'UTF-8');
      $item_id                  = mb_convert_encoding($item_id, 'Shift_JIS', 'UTF-8');
      $item_name                = mb_convert_encoding($item_name, 'Shift_JIS', 'UTF-8');
      $tax                      = mb_convert_encoding($tax, 'Shift_JIS', 'UTF-8');
      $amount                   = mb_convert_encoding($amount, 'Shift_JIS', 'UTF-8');
      $free1                    = mb_convert_encoding($free1, 'Shift_JIS', 'UTF-8');
      $free2                    = mb_convert_encoding($free2, 'Shift_JIS', 'UTF-8');
      $free3                    = mb_convert_encoding($free3, 'Shift_JIS', 'UTF-8');
      $order_rowno              = mb_convert_encoding($order_rowno, 'Shift_JIS', 'UTF-8');
      $sps_cust_info_return_flg = mb_convert_encoding($sps_cust_info_return_flg, 'Shift_JIS', 'UTF-8');
      $cc_number                = mb_convert_encoding($cc_number, 'Shift_JIS', 'UTF-8');
      $cc_expiration            = mb_convert_encoding($cc_expiration, 'Shift_JIS', 'UTF-8');
      $security_code            = mb_convert_encoding($security_code, 'Shift_JIS', 'UTF-8');
      $cust_manage_flg          = mb_convert_encoding($cust_manage_flg, 'Shift_JIS', 'UTF-8');
      $encrypted_flg            = mb_convert_encoding($encrypted_flg, 'Shift_JIS', 'UTF-8');
      $request_date             = mb_convert_encoding($request_date, 'Shift_JIS', 'UTF-8');
      $limit_second             = mb_convert_encoding($limit_second, 'Shift_JIS', 'UTF-8');
      $hashkey                  = mb_convert_encoding($hashkey, 'Shift_JIS', 'UTF-8');

      // 送信情報データ連結
      $result =
          $merchant_id .
          $service_id .
          $cust_code .
          $order_id .
          $item_id .
          $item_name .
          $tax .
          $amount .
          $free1 .
          $free2 .
          $free3 .
          $order_rowno .
          $sps_cust_info_return_flg .
          $cc_number .
          $cc_expiration .
          $security_code .
          $cust_manage_flg .
          $encrypted_flg .
          $request_date .
          $limit_second .
          $hashkey;

      // SHA1変換
      $sps_hashcode = sha1( $result );

      // POSTデータ生成
      $postdata =
          "<?xml version=\"1.0\" encoding=\"Shift_JIS\"?>" .
          "<sps-api-request id=\"ST01-00101-101\">" .
              "<merchant_id>"                 . $merchant_id              . "</merchant_id>" .
              "<service_id>"                  . $service_id               . "</service_id>" .
              "<cust_code>"                   . $cust_code                . "</cust_code>" .
              "<order_id>"                    . $order_id                 . "</order_id>" .
              "<item_id>"                     . $item_id                  . "</item_id>" .
              "<item_name>"                   . base64_encode($item_name) . "</item_name>" .
              "<tax>"                         . $tax                      . "</tax>" .
              "<amount>"                      . $amount                   . "</amount>" .
              "<free1>"                       . base64_encode($free1)     . "</free1>" .
              "<free2>"                       . base64_encode($free2)     . "</free2>" .
              "<free3>"                       . base64_encode($free3)     . "</free3>" .
              "<order_rowno>"                 . $order_rowno              . "</order_rowno>" .
              "<sps_cust_info_return_flg>"    . $sps_cust_info_return_flg . "</sps_cust_info_return_flg>" .
              "<dtls>" .
              "</dtls>" .
              "<pay_method_info>" .
                  "<cc_number>"               . $cc_number                . "</cc_number>" .
                  "<cc_expiration>"           . $cc_expiration            . "</cc_expiration>" .
                  "<security_code>"           . $security_code            . "</security_code>" .
                  "<cust_manage_flg>"         . $cust_manage_flg          . "</cust_manage_flg>" .
              "</pay_method_info>" .
              "<pay_option_manage>" .
              "</pay_option_manage>" .
              "<encrypted_flg>"               . $encrypted_flg            . "</encrypted_flg>" .
              "<request_date>"                . $request_date             . "</request_date>" .
              "<limit_second>"                . $limit_second             . "</limit_second>" .
              "<sps_hashcode>"                . $sps_hashcode             . "</sps_hashcode>" .
          "</sps-api-request>";

      return $postdata;
    }

    public function getRefundDetails() {

      // $query = \Drupal::database();
      

      // API送信データ
      $merchant_id = '';
      $service_id = '';
      $sps_transaction_id = ''; 
      $tracking_id = '';
      $processing_datetime = '';
      $amount = '';
      $request_date = '';
      $limit_second = '';

      // Shift_JIS変換
      $merchant_id              = mb_convert_encoding($merchant_id, 'Shift_JIS', 'UTF-8');
      $service_id               = mb_convert_encoding($service_id, 'Shift_JIS', 'UTF-8');
      $sps_transaction_id       = mb_convert_encoding($sps_transaction_id, 'Shift_JIS', 'UTF-8');
      $tracking_id              = mb_convert_encoding($tracking_id, 'Shift_JIS', 'UTF-8');
      $processing_datetime      = mb_convert_encoding($processing_datetime, 'Shift_JIS', 'UTF-8');
      $amount                   = mb_convert_encoding($amount, 'Shift_JIS', 'UTF-8');
      $request_date             = mb_convert_encoding($request_date, 'Shift_JIS', 'UTF-8');
      $limit_second             = mb_convert_encoding($limit_second, 'Shift_JIS', 'UTF-8');

      // 送信情報データ連結
      $result =
        $merchant_id .
        $service_id .
        $sps_transaction_id .
        $tracking_id .
        $processing_datetime .
        $amount .
        $request_date .
        $limit_second;

      // SHA1変換
      $sps_hashcode = sha1( $result );

      // POSTデータ生成
      $postdata =
          "<?xml version=\"1.0\" encoding=\"Shift_JIS\"?>" .
          "<sps-api-request id=\"ST02-00303-101\">" .
              "<merchant_id>"                 . $merchant_id              . "</merchant_id>" .
              "<service_id>"                  . $service_id               . "</service_id>" .
              "<sps_transaction_id>"          . $sps_transaction_id       . "</sps_transaction_id>" .
              "<tracking_id>"                 . $tracking_id              . "</tracking_id>" .
              "<processing_datetime>"         . $processing_datetime      . "</processing_datetime>" .
              "<amount>"                      . $amount                   . "</amount>" .
              "<request_date>"                . $request_date             . "</request_date>" .
              "<limit_second>"                . $limit_second             . "</limit_second>" .
              "<sps_hashcode>"                . $sps_hashcode             . "</sps_hashcode>" .
          "</sps-api-request>";

      return $postdata;

    }
  }