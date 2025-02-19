<?php
namespace Opencart\Admin\Controller\Extension\ClickpayApplepayPayment\Payment;

use Exception;

class ClickpayApplepayPayment extends \Opencart\System\Engine\Controller
{
  const ENDPOINT_REFUND = "/api/v0/payment/{transactionId}/refund";

  public function index(): void
  {

    $this->load->language('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment');

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
      'href' => $this->url->link('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment', 'user_token=' . $this->session->data['user_token'])
    ];

    $data['save'] = $this->url->link('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment|save', 'user_token=' . $this->session->data['user_token']);
    $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

    //restore form with previous data
    $key_prefix = 'payment_clickpayapplepay_payment_';
    $data[$key_prefix . 'approved_status_id'] = $this->config->get($key_prefix . 'approved_status_id');
    $data[$key_prefix . 'failed_status_id'] = $this->config->get($key_prefix . 'failed_status_id');
    $data[$key_prefix . 'geo_zone_id'] = $this->config->get($key_prefix . 'geo_zone_id');
    $data[$key_prefix . 'status'] = $this->config->get($key_prefix . 'status');
    $data[$key_prefix . 'sort_order'] = $this->config->get($key_prefix . 'sort_order');

    $data[$key_prefix . 'gateway_url'] = $this->config->get($key_prefix . 'gateway_url');
    $data[$key_prefix . 'profile_id'] = $this->config->get($key_prefix . 'profile_id');
    $data[$key_prefix . 'client_key'] = $this->config->get($key_prefix . 'client_key');
    $data[$key_prefix . 'server_key'] = $this->config->get($key_prefix . 'server_key');

    $data[$key_prefix . 'language'] = $this->config->get($key_prefix . 'language');
    $data[$key_prefix . 'gateway_redirect'] = $this->config->get($key_prefix . 'gateway_redirect');

    $data[$key_prefix . 'ap_merchant_id'] = $this->config->get($key_prefix . 'ap_merchant_id');
    $data[$key_prefix . 'ap_cert_file'] = $this->config->get($key_prefix . 'ap_cert_file');
    $data[$key_prefix . 'ap_key_file'] = $this->config->get($key_prefix . 'ap_key_file');

    $data[$key_prefix . 'ap_cert_filename_only'] = "";
    $data[$key_prefix . 'ap_key_filename_only'] = "";    
    if (isset($data[$key_prefix . 'ap_cert_file']) && $data[$key_prefix . 'ap_cert_file'] != null && $data[$key_prefix . 'ap_cert_file'] != "")
      $data[$key_prefix . 'ap_cert_filename_only'] = basename($data[$key_prefix . 'ap_cert_file']);
    if (isset($data[$key_prefix . 'ap_key_file']) &&  $data[$key_prefix . 'ap_key_file'] != null && $data[$key_prefix . 'ap_key_file'] != "")
      $data[$key_prefix . 'ap_key_filename_only'] = basename($data[$key_prefix . 'ap_key_file']);

    $this->load->model('localisation/order_status');
    $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

    $this->load->model('localisation/geo_zone');
    $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();


    $data['header'] = $this->load->controller('common/header');
    $data['column_left'] = $this->load->controller('common/column_left');
    $data['footer'] = $this->load->controller('common/footer');

    if (is_readable('../extension/clickpayapplepay_payment/install.json')) {
      $module_json = file_get_contents('../extension/clickpayapplepay_payment/install.json');
      $module_json = json_decode($module_json, true);
      $data['extension_version'] = "v" . $module_json['version'] . " for oc4.0.2+";
    }

    $this->response->setOutput($this->load->view('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment', $data));
  }

  public function save(): void
  {
    $this->load->language('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment');

    $json = [];

    if (!$this->user->hasPermission('modify', 'extension/clickpayapplepay_payment/payment/clickpayapplepay_payment')) {
      $json['error'] = $this->language->get('error_permission');
    }
    
    if (!$json) {
      $this->load->model('setting/setting');
      $this->model_setting_setting->editSetting('payment_clickpayapplepay_payment', $this->request->post);
      $json['success'] = $this->language->get('text_success');
    }
 

    $key_prefix = 'payment_clickpayapplepay_payment_';
    $key_ap_cert = $key_prefix . 'ap_cert_file';
    $key_ap_key = $key_prefix . 'ap_key_file';    
    $cert_file = "cp_cert_file";
    $key_file = "cp_key_file";

    if (isset($this->request->files[$cert_file]) && is_uploaded_file($this->request->files[$cert_file]['tmp_name'])) {
      // Define the upload directory
      $upload_dir = DIR_UPLOAD . 'clickpayapplepay_payment/';

      // Ensure the directory exists
      if (!is_dir($upload_dir)) {
          mkdir($upload_dir, 0777, true);
      }

      // Define the upload file path
      $file_path = $upload_dir . basename($this->request->files[$cert_file]['name']);

      // Move the uploaded file to the desired location
      if (move_uploaded_file($this->request->files[$cert_file]['tmp_name'], $file_path)) {
          // Save the file path in the settings (to the database)
          $this->load->model('setting/setting');
          $this->model_setting_setting->editValue('payment_clickpayapplepay_payment', $key_ap_cert , $file_path);
      } else {
          // Set an error message if file upload fails
          $json['error'] = $this->language->get('error_upload');
      }
    }

    
    if (isset($this->request->files[$key_file]) && is_uploaded_file($this->request->files[$key_file]['tmp_name'])) {
      // Define the upload directory
      $upload_dir = DIR_UPLOAD . 'clickpayapplepay_payment/';

      // Ensure the directory exists
      if (!is_dir($upload_dir)) {
          mkdir($upload_dir, 0777, true);
      }

      // Define the upload file path
      $file_path = $upload_dir . basename($this->request->files[$key_file]['name']);

      // Move the uploaded file to the desired location
      if (move_uploaded_file($this->request->files[$key_file]['tmp_name'], $file_path)) {
          // Save the file path in the settings (to the database)
          $this->load->model('setting/setting');
          $this->model_setting_setting->editValue('payment_clickpayapplepay_payment', $key_ap_key , $file_path);
      } else {
          // Set an error message if file upload fails
          $json['error'] = $this->language->get('error_upload');
      }
    }


    if (!$json) {
      $this->load->model('setting/setting');
      $this->model_setting_setting->editSetting('payment_clickpayapplepay_payment', $this->request->post);
      $json['success'] = $this->language->get('text_success');
    }

    if (isset($json['error']))
    {
      $this->session->data['error'] = $json['error'];
      $this->url->link('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment', 'user_token=' . $this->session->data['user_token']);
    }
    else
    {
      $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment'));
    }
  }

  public function order()
  {
    $this->load->model('setting/setting');
    $this->load->model('sale/order');
    $this->load->language('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment');

    if ($this->config->get('payment_clickpayapplepay_payment_status')) {

      $data['order_id'] = $this->request->get['order_id'];

      $sale_order = $this->model_sale_order->getOrder($this->request->get['order_id']);

      if ($sale_order && @$sale_order['payment_method']['code'] == 'clickpayapplepay_payment.clickpayapplepay_payment') {
        $data['charge'] = round($sale_order['total'], 2);
        $data['currency'] = $sale_order['currency_code'];

        $data['user_token'] = $this->request->get['user_token'];

        $auth_status_id = $this->config->get('payment_clickpayapplepay_payment_authorized_status_id');
        $data['allow_capture'] = $sale_order['order_status_id'] == $auth_status_id;

        return $this->load->view('extension/clickpayapplepay_payment/payment/clickpayapplepay_order', $data);
      }
    }
  }

  public function refund()
  {

    $this->load->model('setting/setting');
    $this->load->model('sale/order');
    $this->load->language('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment');
    $this->load->model('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment');
    

    //get settings
    $key_prefix = 'payment_clickpayapplepay_payment_';
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

        $cp_order_data = $this->model_extension_clickpayapplepay_payment_payment_clickpayapplepay_payment->fetchClickpayApplepayOrder($order_id);
        $cart_id = $cp_order_data['clickpayapplepay_cart_id'];
        $transaction_ref = $cp_order_data['clickpayapplepay_transaction_ref'];

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
      $this->log->write("clickpayapplepay refund error" . $e->getMessage());
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

  public function install(): void
  {
    if ($this->user->hasPermission('modify', 'extension/payment')) {
      $this->load->model('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment');
      $this->model_extension_clickpayapplepay_payment_payment_clickpayapplepay_payment->install();
    }
  }

  public function uninstall(): void
  {
    if ($this->user->hasPermission('modify', 'extension/payment')) {
      $this->load->model('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment');
      $this->model_extension_clickpayapplepay_payment_payment_clickpayapplepay_payment->uninstall();
    }
  }

}
