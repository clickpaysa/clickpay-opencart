<?php
namespace Opencart\Admin\Model\Extension\ClickpayApplepayPayment\Payment;

class ClickpayApplepayPayment extends \Opencart\System\Engine\Model {
	public function charge(int $customer_id, int $customer_payment_id, float $amount): int {
		$this->load->language('extension/clickpayapplepay_payment/payment/clickpayapplepay_payment');

		$json = [];

		if (!$json) {

		}

		return $this->config->get('config_subscription_active_status_id');
	}

	public function install() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpayapplepay_session`;");
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpayapplepay_order`;");
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpayapplepay_token`;");
		$this->createMissingTables();
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpayapplepay_session`;");
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpayapplepay_order`;");
    $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "clickpayapplepay_token`;");
	}

	public function createMissingTables(){

		//Table to store session when redirect between gateway and shop
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "clickpayapplepay_session` (
			`clickpayapplepay_session_id` INT(11) NOT NULL AUTO_INCREMENT,
			`session_id` VARCHAR(255) NOT NULL,
			`session_data` LONGTEXT,
			PRIMARY KEY (`clickpayapplepay_session_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
		");		

    $this->db->query("
      CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "clickpayapplepay_order` (
        `clickpayapplepay_order_id` INT(11) NOT NULL AUTO_INCREMENT,
        `order_id` VARCHAR(255) NOT NULL,
        `token` VARCHAR(255),
        `clickpayapplepay_cart_id` VARCHAR(255),
        `clickpayapplepay_transaction_ref` VARCHAR(255),
        `clickpayapplepay_order_data` LONGTEXT,
        PRIMARY KEY (`clickpayapplepay_order_id`)
        ) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
    ");		

    $this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "clickpayapplepay_token` (
			`clickpayapplepay_token_id` INT(11) NOT NULL AUTO_INCREMENT,
			`customer_id` INT(11) NOT NULL,
			`credit_card_token` VARCHAR(255),
			PRIMARY KEY (`clickpayapplepay_token_id`)
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;
		");		

	}

  public function fetchClickpayOrder($orderId) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "clickpayapplepay_order` WHERE `order_id` = '" 
          . $this->db->escape($orderId) . "' ORDER BY `clickpayapplepay_order_id` ASC  LIMIT 1");

		return $query->row;
	}

}
