<?php

namespace Opencart\Catalog\Controller\Extension\Clickpay\Payment;

require_once DIR_EXTENSION . 'clickpay/system/library/clickpay_api.php';

use Opencart\System\Library\ClickpayCatalogController;

class ClickpayStcpay extends ClickpayCatalogController
{
	public $_code = 'stcpay';
}
