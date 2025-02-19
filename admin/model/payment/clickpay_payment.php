<?php
namespace Opencart\Admin\Model\Extension\ClickpayPayment\Payment;

class ClickpayPayment extends \Opencart\System\Engine\Model {
	public function charge(int $customer_id, int $customer_payment_id, float $amount): int {
		$this->load->language('extension/clickpay_payment/payment/clickpay_payment');

		$json = [];

		if (!$json) {

		}

		return $this->config->get('config_subscription_active_status_id');
	}

	public function install() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpay_session`;");
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpay_order`;");
		$this->createMissingTables();
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpay_session`;");
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpay_order`;");
	}

	public function createMissingTables(){

		//Table to store session when redirect between gateway and shop
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "clickpay_session` (
			`clickpay_session_id` INT(11) NOT NULL AUTO_INCREMENT,
			`session_id` VARCHAR(255) NOT NULL,
			`session_data` LONGTEXT,
			PRIMARY KEY (`clickpay_session_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
		");		

    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "clickpay_order` (
        `clickpay_order_id` INT(11) NOT NULL AUTO_INCREMENT,
        `order_id` VARCHAR(255) NOT NULL,
        `token` VARCHAR(255),
        `clickpay_cart_id` VARCHAR(255),
        `clickpay_transaction_ref` VARCHAR(255),
        `clickpay_order_data` LONGTEXT,
        PRIMARY KEY (`clickpay_order_id`)
        ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
    ");		

    $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "clickpay_token` (
			`clickpay_token_id` INT(11) NOT NULL AUTO_INCREMENT,
			`customer_id` INT(11) NOT NULL,
			`credit_card_token` VARCHAR(255),
			PRIMARY KEY (`clickpay_token_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
		");		

	}

  public function fetchClickpayOrder($orderId) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "clickpay_order` WHERE `order_id` = '" 
          . $this->db->escape($orderId) . "' ORDER BY `clickpay_order_id` ASC  LIMIT 1");

		return $query->row;
	}

}
