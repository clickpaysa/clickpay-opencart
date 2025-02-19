<?php
namespace Opencart\Catalog\Model\Extension\ClickpayApplepayPayment\Payment;

class ClickpayApplepayPayment extends \Opencart\System\Engine\Model {


	public function getMethods(array $address): array {
		$this->load->language('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment');
		
		
		if (!$this->config->get('payment_clickpayapplepay_payment_geo_zone_id')) {
			$status = true;
		} elseif(isset($address['country_id']) && isset($address['zone_id'])) {
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . (int)$this->config->get('payment_clickpayapplepay_payment_geo_zone_id') . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . (int)$address['zone_id'] . "' OR `zone_id` = '0')");
			if ($query->num_rows) {
				$status = true;
			} else {
				$status = false;
			}
		}else{
			$status = false;
		}

		$method_data = [];

		if ($status) {
			$option_data['clickpayapplepay_payment'] = [
				'code' => 'clickpayapplepay_payment.clickpayapplepay_payment',
				'name' => $this->language->get('heading_title')
			];

			$method_data = [
				'code'       => 'clickpayapplepay_payment',
				'name'       => $this->language->get('heading_title'),
				'option'     => $option_data,
				'sort_order' => $this->config->get('payment_clickpayapplepay_payment_sort_order')
			];
		}

		return $method_data;
	}

	public function storeSession($data) {

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "clickpayapplepay_session` WHERE `session_id` = '" . $this->db->escape($data['session_id']) . "' ORDER BY `clickpayapplepay_session_id` ASC  LIMIT 1");

		if($query->num_rows){
			$this->db->query("UPDATE  `" . DB_PREFIX . "clickpayapplepay_session` SET `session_data` = '" .  $this->db->escape($data['session_data'])  . "' WHERE `session_id` = '" . $this->db->escape($data['session_id']) . "'");
		}else{
			$this->db->query("INSERT INTO `" . DB_PREFIX . "clickpayapplepay_session` SET `session_id` = '" . $this->db->escape($data['session_id']) . "', `session_data` = '" . $this->db->escape($data['session_data']) . "'");
		}

	}

	public function fetchSession($sessionId) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "clickpayapplepay_session` WHERE `session_id` = '" . $this->db->escape($sessionId) . "' ORDER BY `clickpayapplepay_session_id` ASC  LIMIT 1");

		return $query->row;
	}

  public function storeOrder($data) {

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "clickpayapplepay_order` WHERE `order_id` = '" . $this->db->escape($data['order_id']) . "' ORDER BY `clickpayapplepay_order_id` ASC  LIMIT 1");

		if($query->num_rows){
			$this->db->query("UPDATE  `" . DB_PREFIX . "clickpayapplepay_order` SET `token` = '" .  $this->db->escape($data['token'])  . "' WHERE `order_id` = '" . $this->db->escape($data['order_id']) . "'");
		}else{
			$this->db->query("INSERT INTO `" . DB_PREFIX . "clickpayapplepay_order` SET `order_id` = '" . $this->db->escape($data['order_id']) . "', `token` = '" . $this->db->escape($data['token']) . "'");
		}

	}

  public function updateOrder($data) {

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "clickpayapplepay_order` WHERE `order_id` = '" . $this->db->escape($data['order_id']) . "' ORDER BY `clickpayapplepay_order_id` ASC  LIMIT 1");

		if($query->num_rows){
      if (isset($data["clickpayapplepay_cart_id"])) {
			  $this->db->query("UPDATE  `" . DB_PREFIX . "clickpayapplepay_order` SET `clickpayapplepay_cart_id` = '" .  $this->db->escape($data['clickpayapplepay_cart_id'])  . "' WHERE `order_id` = '" . $this->db->escape($data['order_id']) . "'");
      }
      if (isset($data["clickpayapplepay_transaction_ref"])) {
			  $this->db->query("UPDATE  `" . DB_PREFIX . "clickpayapplepay_order` SET `clickpayapplepay_transaction_ref` = '" .  $this->db->escape($data['clickpayapplepay_transaction_ref'])  . "' WHERE `order_id` = '" . $this->db->escape($data['order_id']) . "'");
      }
    }
	}


	public function fetchOrder($orderId) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "clickpayapplepay_order` WHERE `order_id` = " . $this->db->escape($orderId) . " LIMIT 1");

		return $query->row;
	}

  public function updateCustomerToken($data) {

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "clickpayapplepay_token` WHERE `customer_id` = '" . $this->db->escape($data['customer_id']) 
          . "' ORDER BY `clickpayapplepay_token_id` ASC  LIMIT 1");

		if($query->num_rows){
			  $this->db->query("UPDATE  `" . DB_PREFIX . "clickpayapplepay_token` SET `credit_card_token` = '" .  $this->db->escape($data['token'])  
            . "' WHERE `customer_id` = '" . $this->db->escape($data['customer_id']) . "'");    
    }
    else {
      $this->db->query("INSERT INTO `" . DB_PREFIX . "clickpayapplepay_token`(customer_id,credit_card_token) VALUES (" 
        . $this->db->escape($data['customer_id']) . ", '" . $this->db->escape($data['token']) . "')"  );
    }
	}

  public function fetchToken($customerId) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "clickpayapplepay_token` WHERE `customer_id` = " . $this->db->escape($customerId) . " LIMIT 1");

		return $query->row;
	}

	
	public function log($data, $class_step = 6, $function_step = 6) {
		if ($this->config->get('payment_clickpayapplepay_debug') || true) {
			$backtrace = debug_backtrace();
			$log = new Log('clickpayapplepay.log');
			$log->write('(' . $backtrace[$class_step]['class'] . '::' . $backtrace[$function_step]['function'] . ') - ' . print_r($data, true));
		}
	}
}
