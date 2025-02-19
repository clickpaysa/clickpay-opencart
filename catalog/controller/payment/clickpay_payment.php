<?php
namespace Opencart\Catalog\Controller\Extension\ClickpayPayment\Payment;

class ClickpayPayment extends \Opencart\System\Engine\Controller
{

  /**
   * Set session to database to preserve between redirects
   *
   * @return void
   */
  private function setSession()
  {
    $this->load->model('extension/clickpay_payment/payment/clickpay_payment');

    $rawSessionData = $this->session->data;

    //serialize
    $serializedSession = json_encode($rawSessionData);

    try {
      $this->model_extension_clickpay_payment_payment_clickpay_payment->storeSession(array(
        'session_id' => $this->session->getId(),
        'session_data' => $serializedSession,
      ));
    } catch (Exception $e) {
      $this->log->write("db error" . $e->getMessage());
    }

  }

  /**
   * Restore session from database
   *
   * @param [type] $sessionId
   * @return string
   */
  private function getSession($sessionId): string
  {

    $this->load->model('extension/clickpay_payment/payment/clickpay_payment');

    //if session lost, restore from database
    if ($this->session->getId() != $sessionId) {
      $sessionDataOnDB = $this->model_extension_clickpay_payment_payment_clickpay_payment->fetchSession($sessionId);
      //unserialize
      $this->session->data = json_decode(($sessionDataOnDB['session_data']), true);
    }

    return $this->session->data['order_id'];

  }


  public function index(): mixed
  {
    $this->load->language('extension/clickpay_payment/payment/clickpay_payment');
    $this->load->model('checkout/order');
    
    $key_prefix = 'payment_clickpay_payment_';
    $data = array();
    $data['button_confirm'] = $this->language->get('button_confirm');    
    $data['gateway_redirect'] =  $this->config->get($key_prefix . 'gateway_redirect');
    $data['client_key'] =  $this->config->get($key_prefix . 'client_key');
    $data['text_title'] = $this->language->get('text_title');    
    $data['text_description'] = $this->language->get('text_description');    

    $imagePath = 'extension/clickpay_payment/catalog/view/theme/default/image/';
    $imageUrl = HTTP_SERVER . $imagePath;
    
    $data['image_url'] = $imageUrl;

    return $this->load->view('extension/clickpay_payment/payment/clickpay_payment', $data);

  }

  public function send()
  {
    $this->load->language('extension/clickpay_payment/payment/clickpay_payment');
    $this->load->model('extension/clickpay_payment/payment/clickpay_payment');
    $this->load->model('checkout/order');

    $order_id = $this->session->data['order_id'];
    $return_url = $this->url->link('extension/clickpay_payment/payment/clickpay_payment.callback&session=' . $this->session->getId() . '&order_id=' . $order_id, '', true);
    $cancel_url = $this->url->link('checkout/checkout', '', true);

    $customer_id = 0;
    if ($this->customer->isLogged()) {      
      $customer_id = $this->customer->getId();
    }

    //get settings
    $key_prefix = 'payment_clickpay_payment_';
    $gateway_url = $this->config->get($key_prefix . 'gateway_url');
    $profile_id = $this->config->get($key_prefix . 'profile_id');
    $client_key = $this->config->get($key_prefix . 'client_key');
    $server_key = $this->config->get($key_prefix . 'server_key');
    $language = $this->config->get($key_prefix . 'language');
    $payment_action = $this->config->get($key_prefix . 'payment_action');
    $allow_save_cards = $this->config->get($key_prefix . 'allow_saved_cards');
    $gateway_redirect = $this->config->get($key_prefix . 'gateway_redirect');

    $error = "";

    $order_info = $this->model_checkout_order->getOrder($order_id);

    $products = $this->cart->getProducts();

    $currency = $this->session->data['currency'];
    $shipping_name = trim($order_info['shipping_firstname'] . ' ' . $order_info['shipping_lastname']);
    $shipping_address = $order_info['shipping_address_1'];
    if ($order_info['shipping_address_2']) {
      $shipping_address = $shipping_address . " " . $order_info['shipping_address_2'];
    }
    $shipping_city = $order_info['shipping_city'];
    $shipping_postCode = $order_info['shipping_postcode'];
    $shipping_state = $order_info['shipping_zone'];
    $shipping_country = $order_info['shipping_country'];
    $phone = $order_info['telephone'];
    $email = $order_info['email'];
    $total = $order_info['total'];

    $billing_name = trim($order_info['payment_firstname'] . ' ' . $order_info['payment_lastname']);
    if ($billing_name == '')
      $billing_name = $shipping_name;
    $billing_address = $order_info['payment_address_1'];
    if ($order_info['payment_address_2']) {
      $billing_address = $billing_address . " " . $order_info['payment_address_2'];
    }
    $billing_city = $order_info['payment_city'];
    $billing_postCode = $order_info['payment_postcode'];
    $billing_state = $order_info['payment_zone'];
    $billing_country = $order_info['payment_country'];

    $productInfo = "";
    foreach ($products as $product) {
      $productInfo = $productInfo . $product['name'] . ' ';
    }
    if ($productInfo == "")
      $productInfo = "Products";
    else
      $productInfo = substr(trim($productInfo), 0, 100);


    $tmp_order_id = $order_id . '_' . date("ymdHis") . ':' . rand(1, 1000);

    $tran_class = "ecom";    //hard coded value

    $data = [
      "tran_type" => $payment_action,
      "tran_class" => $tran_class,
      "cart_id" => $tmp_order_id,
      "cart_currency" => $currency,
      "cart_amount" => $total,
      "cart_description" => $productInfo,
      "paypage_lang" => $language,
      "show_save_card" => $allow_save_cards ? true : false,
      "callback" => null,  //webhook called by the server
      "return" => $return_url,      //url called after user completes the form
    ];

   if ($allow_save_cards && $this->customer->isLogged()) {
      
      $this->log->write("Getting saved cards");
      
      $data['tokenise'] = 2;
      $token = $this->get_card_details($customer_id);
      if ($token != null && $token != "") {
        $data['token'] = $token;
      }
    }

    $customer_details = [
      "name" => $billing_name,
      "email" => $email,
      "phone" => $phone,
      "street1" => $billing_address,
      "city" => $billing_city,
      "state" => $billing_state,
      "country" => $billing_country,
      "zip" => $billing_postCode
    ];

    $shipping_details = [
      "name" => $shipping_name,
      "email" => $email,
      "phone" => $phone,
      "street1" => $shipping_address,
      "city" => $shipping_city,
      "state" => $shipping_state,
      "country" => $shipping_country,
      "zip" => $shipping_postCode
    ];

    $user_defined = [
      "udf1" => "Opencart",
    ];

    $plugin_info = [
      "cart_name" => "Opencart",
      "cart_version" => VERSION,
      "plugin_version" => "5.0.0"
    ];

    $data['customer_details'] = $customer_details;
    $data['shipping_details'] = $shipping_details;
    $data['user_defined'] = $user_defined;
    $data['plugin_info'] = $plugin_info;
    
    if ($gateway_redirect == "iframe") {
      $data["framed"] = true;
      $data["hide_shipping"] = true;
    }

    $this->log->write("request: " . print_r($data, true));

    $request_url = $gateway_url . 'payment/request';
    $response = $this->send_api_request($request_url, $data, $profile_id, $server_key);

    $this->log->write("response: " . print_r($response, true));

    $action = "";
    if ($response['status'] == 'success') {
      $rdata = $response['data'];
      if (isset($rdata['redirect_url']) && !empty($rdata['redirect_url'])) {
        $action = $rdata['redirect_url'];
      }
    }

    if ($action !== '') {
      $error = "";
    } else {
      $error = "Error processing order. Please try again.";
    }

    if ($error != "") {
      $json = [
        'error' => $error
      ];
    } else {
      $json = [
        'redirect' => $action,
        'method' => $gateway_redirect
      ];

      try {
        $this->setSession();
        $this->model_extension_clickpay_payment_payment_clickpay_payment->storeOrder(array(
          'order_id' => $order_id,
          'token' => '',
        ));
      } catch (Exception $e) {
        $this->log->write("db error" . $e->getMessage());
        throw $e;        
      }
  
    }

    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }

  function get_card_details($customer_id)
  {
    $token = "";
    if ($customer_id)
    {
      $tokenData = $this->model_extension_clickpay_payment_payment_clickpay_payment->fetchToken($customer_id);

      $this->log->write("Fetched Token: " . print_r($tokenData, true));

      if (isset($tokenData['credit_card_token']))
        $token = $tokenData['credit_card_token'];
    }

    return $token;
  }


  function save_card_details($customer_id, $token)
  {
    if ($customer_id > 0 && $token != null && $token != "")
    {
      $data['customer_id'] = $customer_id;
      $data['token'] = $token;

      $this->log->write("Calling update customer token " . print_r($data, true));

      $this->model_extension_clickpay_payment_payment_clickpay_payment->updateCustomerToken($data);      
    }

    return $token;
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


  // Method called by the PG after the hosted form / iframe completes
  public function callback()
  {

    $this->log->write("Callback Data Received " . print_r($_POST, true));
    $response_data = $_POST;

    $this->load->language('extension/clickpay_payment/payment/clickpay_payment');
    $this->load->model('extension/clickpay_payment/payment/clickpay_payment');
    $this->load->model('checkout/order');

    //get settings
    $key_prefix = 'payment_clickpay_payment_';
    $gateway_url = $this->config->get($key_prefix . 'gateway_url');
    $profile_id = $this->config->get($key_prefix . 'profile_id');
    $client_key = $this->config->get($key_prefix . 'client_key');
    $server_key = $this->config->get($key_prefix . 'server_key');
    $language = $this->config->get($key_prefix . 'language');
    $payment_action = $this->config->get($key_prefix . 'payment_action');
    $allow_save_cards = $this->config->get($key_prefix . 'allow_saved_cards');
    $gateway_redirect = $this->config->get($key_prefix . 'gateway_redirect');


    $json = [];
    $is_success = false;
    $is_valid = false;
    $card_token = "";
    $error = "";
    $customer_id = 0;

    $trans_ref = filter_input(INPUT_POST, 'tranRef');
    if ($trans_ref && $trans_ref != null && $trans_ref != "") {
      $is_valid = $this->is_valid_redirect($response_data, $server_key);
    }

    $cartid = "";
    //check for payment success / failure
    if ($is_valid && isset($response_data['cartId']) && !empty($response_data['cartId'])) {

      $cartid = $response_data['cartId'];
      //call inquiry and confirm transaction is successful
      $response_inquiry = $this->payment_inquiry($trans_ref, $gateway_url, $profile_id, $server_key);

      $this->log->write("Check Payment Inquiry " . print_r($response_inquiry, true));

      if (isset($response_inquiry['status']) && $response_inquiry['status'] == 'success') {
        if (isset($response_inquiry['data']['payment_result']['response_status']))
          $is_success = $response_inquiry['data']['payment_result']['response_status'] === 'A';

          if ($is_success && $allow_save_cards)
          {
            $card_token = isset($response_data["token"]) ? $response_data["token"] : '';
          }
      }
    }

    $getdata = array();
    //sanitize entire response
    foreach ($_GET as $key => $val) {
      $getdata[$key] = filter_var($val, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    $sessionId = $getdata['session'];

    $this->log->write("Parameters received in callbak: " . print_r($getdata, true));

    $order_id = "";
    if (isset($this->session->data['order_id'])) {
      $order_id = $this->session->data['order_id'];
    } else {    
      $this->getSession($sessionId);
      $order_id = $this->session->data['order_id'];
    }

    if ($order_id == "") {
      //order not found            
      $this->log->write("Order was not found in session");
      $json["error"] = "No order found";
      $json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);

    } else {

      $order_info = $this->model_checkout_order->getOrder($order_id);

      $this->log->write("Callback order info: " . print_r($order_info, true));

      //for sanity check order id received in response and that in session match
      $cp_order_id = explode('_', $cartid);      
      $cp_order_id = $cp_order_id[0];    //get rid of time part

      $this->log->write("CP Order Id: " . $cp_order_id);

      if ($cp_order_id == $order_id) {
        $orddata = $this->model_extension_clickpay_payment_payment_clickpay_payment->fetchOrder($order_id);
        
        try {
          $this->model_extension_clickpay_payment_payment_clickpay_payment->updateOrder(array(
            'order_id' => $order_id,
            'clickpay_cart_id' => $cartid,
            'clickpay_transaction_ref' => $trans_ref
          ));
        } catch (Exception $e) {
          $this->log->write("db error" . $e->getMessage());
        }

        if ($is_success) {
          $message = 'Clickpay Cart Id: ' . $cartid;
          $message .= '  Transaction Ref: ' . $trans_ref;
        } else {
          $message = 'Payment attempt failed';
        }

        if ($is_success) {
          $order_status = $this->config->get('payment_clickpay_payment_approved_status_id');
          if ($payment_action == "auth") {
            $order_status = $this->config->get('payment_clickpay_payment_authorized_status_id');
            $message = "Payment has been Authorized " . $message;
          }
          $this->model_checkout_order->addHistory($order_id, $order_status, $message, true);

          //check and save card token
          $customer_id = isset($order_info['customer_id']) ? $order_info['customer_id'] : 0;
          if ($allow_save_cards && $card_token != "" && $customer_id > 0)
          {
            $this->log->write("Saving cards " . $card_token . " customer id " . $customer_id);           
            $this->save_card_details($customer_id, $card_token);
          }

          $json['redirect'] = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'));
        } else {
          $order_status = $this->config->get('payment_clickpay_payment_failed_status_id');
          $this->model_checkout_order->addHistory($order_id, $order_status, $message, true);
          $json['error'] = $message;
          $json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);
        }
      }
      else
      {
        $is_success = false;
        $message = 'Payment attempt failed';
        $json['error'] = $message;
        $json['redirect'] = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);
      }
    }
    
    if ($gateway_redirect == 'iframe')
    {
      $html = '<script type="text/javascript">window.parent.location.href = "' . $json['redirect'] . '";</script>';
      echo $html;
    }
    else {
      return $this->response->redirect($json['redirect']);
    }
  }

  private function payment_inquiry($trans_ref, $gateway_url, $profile_id, $server_key)
  {
    $request_url = $gateway_url . 'payment/query';
    $data = [
      "tran_ref" => $trans_ref
    ];
    $response = $this->send_api_request($request_url, $data, $profile_id, $server_key);

    return $response;
  }

  public function send_api_request($request_url, $data, $profileid, $serverkey, $request_method = null)
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
      $this->log->write("ClickPay curl error: " . print_r($curl_error, true));
      $response = ['status' => 'error'];
    } else {
      $data = json_decode($curl_response, true);
      $response = ['status' => 'success', 'data' => $data];
    }

    return $response;
  }

  //DELETE IF NOT USED
  private function http_get($header, $env, $route)
  {

    if (str_ends_with($env, '/')) {
      $url = rtrim($env, '/') . $route;
    } else {
      $url = $env . $route;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, array(
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => $header,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_FAILONERROR => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_SSL_VERIFYHOST => 0,
      CURLOPT_SSL_VERIFYPEER => 0,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
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

  public static function is_valid_redirect($post_values, $server_key)
  {

    // Request body include a signature post Form URL encoded field
    // 'signature' (hexadecimal encoding for hmac of sorted post form fields)
    $requestSignature = $post_values["signature"];
    unset($post_values["signature"]);
    $fields = array_filter($post_values);

    // Sort form fields
    ksort($fields);

    // Generate URL-encoded query string of Post fields except signature field.
    $query = http_build_query($fields);

    $signature = hash_hmac('sha256', $query, $server_key);
    if (hash_equals($signature, $requestSignature) === TRUE) {
      // VALID Redirect
      return true;
    } else {
      // INVALID Redirect
      return false;
    }
  }

  public function cancel(): string
  {
    $this->getSession($_GET['session']);
    $redirect = $this->url->link('checkout/cart', 'language=' . $this->config->get('config_language'), true);
    return $this->response->redirect($redirect);
  }

  public function verify_payment($environment_url, $order_id, $token, $access_key)
  {


  }
}
