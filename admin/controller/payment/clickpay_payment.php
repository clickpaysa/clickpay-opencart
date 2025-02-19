<?php
namespace Opencart\Admin\Controller\Extension\ClickpayPayment\Payment;

use Exception;

class ClickpayPayment extends \Opencart\System\Engine\Controller
{
  const ENDPOINT_REFUND = "/api/v0/payment/{transactionId}/refund";

  public function index(): void
  {

    $this->load->language('extension/clickpay_payment/payment/clickpay_payment');

    $this->document->setTitle($this->language->get('heading_title'));

    $data['breadcrumbs'] = [];

    $data['breadcrumbs'][] = [
      'text' => $this->language->get('text_home'),
      'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
    ];

    $data['breadcrumbs'][] = [
      'text' => $this->language->get('text_extension'),
      'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
    ];

    $data['breadcrumbs'][] = [
      'text' => $this->language->get('heading_title'),
      'href' => $this->url->link('extension/clickpay_payment/payment/clickpay_payment', 'user_token=' . $this->session->data['user_token'])
    ];

    $data['save'] = $this->url->link('extension/clickpay_payment/payment/clickpay_payment|save', 'user_token=' . $this->session->data['user_token']);
    $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

    //restore form with previous data
    $key_prefix = 'payment_clickpay_payment_';
    $data[$key_prefix . 'approved_status_id'] = $this->config->get($key_prefix . 'approved_status_id');
    $data[$key_prefix . 'failed_status_id'] = $this->config->get($key_prefix . 'failed_status_id');
    $data[$key_prefix . 'authorized_status_id'] = $this->config->get($key_prefix . 'authorized_status_id');
    $data[$key_prefix . 'geo_zone_id'] = $this->config->get($key_prefix . 'geo_zone_id');
    $data[$key_prefix . 'status'] = $this->config->get($key_prefix . 'status');
    $data[$key_prefix . 'sort_order'] = $this->config->get($key_prefix . 'sort_order');

    $data[$key_prefix . 'gateway_url'] = $this->config->get($key_prefix . 'gateway_url');
    $data[$key_prefix . 'profile_id'] = $this->config->get($key_prefix . 'profile_id');
    $data[$key_prefix . 'client_key'] = $this->config->get($key_prefix . 'client_key');
    $data[$key_prefix . 'server_key'] = $this->config->get($key_prefix . 'server_key');

    $data[$key_prefix . 'payment_action'] = $this->config->get($key_prefix . 'payment_action');
    $data[$key_prefix . 'language'] = $this->config->get($key_prefix . 'language');
    $data[$key_prefix . 'allow_saved_cards'] = $this->config->get($key_prefix . 'allow_saved_cards');
    $data[$key_prefix . 'gateway_redirect'] = $this->config->get($key_prefix . 'gateway_redirect');

    $this->load->model('localisation/order_status');
    $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

    $this->load->model('localisation/geo_zone');
    $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();


    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    if (is_readable('../extension/clickpay_payment/install.json')) {
      $module_json = file_get_contents('../extension/clickpay_payment/install.json');
      $module_json = json_decode($module_json, true);
      $data['extension_version'] = "v" . $module_json['version'] . " for oc4.0.2+";
    }

    $this->response->setOutput($this->load->view('extension/clickpay_payment/payment/clickpay_payment', $data));
  }

  public function save(): void
  {
    $this->load->language('extension/clickpay_payment/payment/clickpay_payment');

    $json = [];

    if (!$this->user->hasPermission('modify', 'extension/clickpay_payment/payment/clickpay_payment')) {
      $json['error'] = $this->language->get('error_permission');
    }

    if (!$json) {
      $this->load->model('setting/setting');

      $this->model_setting_setting->editSetting('payment_clickpay_payment', $this->request->post);

      $json['success'] = $this->language->get('text_success');
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }

  public function order()
  {
    $this->load->model('setting/setting');
    $this->load->model('sale/order');
    $this->load->language('extension/clickpay_payment/payment/clickpay_payment');

    if ($this->config->get('payment_clickpay_payment_status')) {

      $data['order_id'] = $this->request->get['order_id'];

      $sale_order = $this->model_sale_order->getOrder($this->request->get['order_id']);

      if ($sale_order && @$sale_order['payment_method']['code'] == 'clickpay_payment.clickpay_payment') {
        $data['charge'] = round($sale_order['total'], 2);
        $data['currency'] = $sale_order['currency_code'];

        $data['user_token'] = $this->request->get['user_token'];

        $auth_status_id = $this->config->get('payment_clickpay_payment_authorized_status_id');
        $data['allow_capture'] = $sale_order['order_status_id'] == $auth_status_id;

        return $this->load->view('extension/clickpay_payment/payment/clickpay_order', $data);
      }
    }
  }

  public function refund()
  {

    $this->load->model('setting/setting');
    $this->load->model('sale/order');
    $this->load->language('extension/clickpay_payment/payment/clickpay_payment');
    $this->load->model('extension/clickpay_payment/payment/clickpay_payment');
    

    //get settings
    $key_prefix = 'payment_clickpay_payment_';
    $profile_id = $this->config->get($key_prefix . 'profile_id');
    $server_key = $this->config->get($key_prefix . 'server_key');
    $gateway_url = $this->config->get($key_prefix . 'gateway_url');

    $json = array();
    $isSuccess = false;

    try {

      if (isset($this->request->post['order_id']) && $this->request->post['order_id'] != '') {

        $order_id = $this->request->post['order_id'];
        $order_info = $this->model_sale_order->getOrder($order_id);

        $currency = $order_info['currency_code'];
        $amount = $this->request->post['amount'];

        $cp_order_data = $this->model_extension_clickpay_payment_payment_clickpay_payment->fetchClickpayOrder($order_id);
        $cart_id = $cp_order_data['clickpay_cart_id'];
        $transaction_ref = $cp_order_data['clickpay_transaction_ref'];

        $this->log->write("Refund Cart ID " . $cart_id . ' Ref: ' . $transaction_ref);

        if ($cart_id != '' && $transaction_ref != '') {

          $tran_class = "ecom";    //hard coded value

          $data = [
            "tran_type" => 'refund',
            "tran_class" => $tran_class,
            "cart_id" => $cart_id,
            "cart_currency" => $currency,
            "cart_amount" => $amount,
            "cart_description" => "Shopping Cart",
            "tran_ref" => $transaction_ref,
          ];

          $request_url = $gateway_url . 'payment/request';
          $response = $this->send_api_request($request_url, $data, $profile_id, $server_key);

          if (isset($response['status']) && $response['status'] == 'success') {
            if (isset($response['data']['payment_result']['response_status']))
              $isSuccess = $response['data']['payment_result']['response_status'] === 'A';
          }
        }

        if ($isSuccess) {
          $this->log->write("Setting status to refunded");
          $order_status = 11;
          // Add the history to the order_history table
          $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET 
            `order_id` = '" . (int)$order_id . "',
            `order_status_id` = '" . (int)$order_status . "',
            `notify` = '1', 
            `comment` = 'Order has been refunded.', 
            `date_added` = NOW()");
  
          // Update the order status in the order table
          $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
            `order_status_id` = '" . (int)$order_status . "' 
            WHERE `order_id` = '" . (int)$order_id . "'");
        }

      } else {
        $isSuccess = false;
        $message = "Error processing refund. Invalid Cart ID / Transaction Ref.";
      }
    } catch (Exception $e) {
      $this->log->write("clickpay refund error" . $e->getMessage());
    }

    if ($isSuccess) {
      $json['error'] = false;
      $json['msg'] = "Refund is successfull.";
    } else {
      $json['error'] = true;
      $json['msg'] = "Refund could not be processed. Try again!";
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));

  }

  public function capture()
  {

    $this->load->model('setting/setting');
    $this->load->model('sale/order');
    $this->load->language('extension/clickpay_payment/payment/clickpay_payment');
    $this->load->model('extension/clickpay_payment/payment/clickpay_payment');
    

    //get settings
    $key_prefix = 'payment_clickpay_payment_';
    $profile_id = $this->config->get($key_prefix . 'profile_id');
    $server_key = $this->config->get($key_prefix . 'server_key');
    $gateway_url = $this->config->get($key_prefix . 'gateway_url');

    $json = array();
    $isSuccess = false;

    try {

      if (isset($this->request->post['order_id']) && $this->request->post['order_id'] != '') {

        $order_id = $this->request->post['order_id'];
        $order_info = $this->model_sale_order->getOrder($order_id);

        $currency = $order_info['currency_code'];
        $total = $order_info['total'];

        $cp_order_data = $this->model_extension_clickpay_payment_payment_clickpay_payment->fetchClickpayOrder($order_id);
        $cart_id = $cp_order_data['clickpay_cart_id'];
        $transaction_ref = $cp_order_data['clickpay_transaction_ref'];

        if ($cart_id != '' && $transaction_ref != '') {

          $tran_class = "ecom";    //hard coded value

          $tran_class = "ecom";    //hard coded value

          $data = [
            "tran_type" => 'capture',
            "tran_class" => $tran_class,
            "cart_id" => $cart_id,
            "cart_currency" => $currency,
            "cart_amount" => $total,
            "cart_description" => "Payment captured",
            "tran_ref" => $transaction_ref,
          ];

          $request_url = $gateway_url . 'payment/request';
          $response = $this->send_api_request($request_url, $data, $profile_id, $server_key);

          if (isset($response['status']) && $response['status'] == 'success') {
            if (isset($response['data']['payment_result']['response_status']))
              $isSuccess = $response['data']['payment_result']['response_status'] === 'A';
          }
        }

        if ($isSuccess) {
          $this->log->write("Setting status to captured");
          $order_status = $order_info['order_status_id'];
          // Add the history to the order_history table
          $this->db->query("INSERT INTO `" . DB_PREFIX . "order_history` SET 
            `order_id` = '" . (int)$order_id . "',
            `order_status_id` = '" . (int)$order_status . "',
            `notify` = '1', 
            `comment` = 'Payment captured successfully.', 
            `date_added` = NOW()");
  
          // Update the order status in the order table
          // $this->db->query("UPDATE `" . DB_PREFIX . "order` SET 
          //   `order_status_id` = '" . (int)$order_status . "' 
          //   WHERE `order_id` = '" . (int)$order_id . "'");
        }

      } else {
        $isSuccess = false;
        $message = "Error processing capture. Invalid Cart ID / Transaction Ref.";
      }
    } catch (Exception $e) {
      $this->log->write("clickpay capture error" . $e->getMessage());
    }

    if ($isSuccess) {
      $json['error'] = false;
      $json['msg'] = "Capture is successfull.";
    } else {
      $json['error'] = true;
      $json['msg'] = "Capture could not be processed. Try again!";
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));

  }


  public static function send_api_request($request_url, $data, $profileid, $serverkey, $request_method = null)
  {
    $data['profile_id'] = $profileid;
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $request_url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_SSL_VERIFYHOST => false,
      CURLOPT_CUSTOMREQUEST => isset($request_method) ? $request_method : 'POST',
      CURLOPT_POSTFIELDS => json_encode($data, true),
      CURLOPT_HTTPHEADER => array(
          'authorization:' . $serverkey,
          'Content-Type:application/json'
        ),
    ));

    $curl_response = curl_exec($curl);
    $curl_error = curl_error($curl);
    curl_close($curl);

    $response = [];
    if ($curl_error != '') {
      error_log("ClickPay curl error: " . print_r($curl_error, true));
      $response = ['status' => 'error'];
    } else {
      $data = json_decode($curl_response, true);
      $response = ['status' => 'success', 'data' => $data];
    }

    return $response;
  }

  public function install(): void
  {
    if ($this->user->hasPermission('modify', 'extension/payment')) {
      $this->load->model('extension/clickpay_payment/payment/clickpay_payment');

      $this->model_extension_clickpay_payment_payment_clickpay_payment->install();
    }
  }

  public function uninstall(): void
  {
    if ($this->user->hasPermission('modify', 'extension/payment')) {
      $this->load->model('extension/clickpay_payment/payment/clickpay_payment');

      $this->model_extension_clickpay_payment_payment_clickpay_payment->uninstall();
    }
  }

  function build_header($token = null, $accesskey = '')
  {
    $header = array(
      'Content-Type: application/json',
      'charset: utf-8'
    );
    if (!empty($token)) {
      array_push($header, 'Authorization: ' . $token);
      array_push($header, 'merchantAccessKey: ' . $accesskey);
    }
    return $header;
  }

  function http_post($header, $data, $env, $route)
  {
    foreach (@$data as $key => $value) {
      if (empty($data[$key])) {
        unset($data[$key]);
      }
    }

    $url = "";
    if (str_ends_with($env, '/')) {
      $url = rtrim($env, '/') . $route;
    } else {
      $url = $env . $route;
    }

    $curl = curl_init($url);

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => 0,
      CURLOPT_POSTFIELDS => json_encode($data, JSON_NUMERIC_CHECK),
      CURLOPT_HTTPHEADER => $header,
    ));

    $response = curl_exec($curl);
    $error_msg = "";
    if (curl_errno($curl)) {
      $error_msg = curl_error($curl);
    }
    curl_close($curl);

    if ($error_msg == "")
      return $response;
    else
      return "ERROR: " . $error_msg;
  }


}
